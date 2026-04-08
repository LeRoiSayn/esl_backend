<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Models\ClassModel;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function adminStats()
    {
        return $this->success(Cache::remember('dashboard:admin', now()->addSeconds(45), function () {
            // 7 COUNTs → 2 queries using conditional aggregation (PostgreSQL FILTER syntax)
            $studentRow = DB::selectOne("
                SELECT COUNT(*) AS total,
                       COUNT(*) FILTER (WHERE status = 'active') AS active
                FROM students
            ");
            $teacherRow = DB::selectOne("
                SELECT COUNT(*) AS total,
                       COUNT(*) FILTER (WHERE status = 'active') AS active
                FROM teachers
            ");
            $miscRow = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM courses)     AS total_courses,
                    (SELECT COUNT(*) FROM departments) AS total_departments,
                    (SELECT COUNT(*) FROM faculties)   AS total_faculties
            ");

            $stats = [
                'total_students'    => (int) ($studentRow->total    ?? 0),
                'active_students'   => (int) ($studentRow->active   ?? 0),
                'total_teachers'    => (int) ($teacherRow->total    ?? 0),
                'active_teachers'   => (int) ($teacherRow->active   ?? 0),
                'total_courses'     => (int) ($miscRow->total_courses     ?? 0),
                'total_departments' => (int) ($miscRow->total_departments ?? 0),
                'total_faculties'   => (int) ($miscRow->total_faculties   ?? 0),
            ];

            // Enrollment trends (last 6 months)
            $enrollmentTrends = Student::selectRaw("TO_CHAR(enrollment_date, 'YYYY-MM') as month, COUNT(*) as count")
                ->where('enrollment_date', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Students by department (avoid loading full Department models)
            $studentsByDepartment = DB::table('departments')
                ->leftJoin('students', 'students.department_id', '=', 'departments.id')
                ->where('departments.is_active', true)
                ->groupBy('departments.id', 'departments.name')
                ->selectRaw('departments.name AS name, COUNT(students.id) AS count')
                ->orderBy('departments.name')
                ->get();

            // Students by level
            $studentsByLevel = Student::selectRaw('level, COUNT(*) as count')
                ->groupBy('level')
                ->get();

            // Recent students (keep payload small)
            $recentStudents = Student::with([
                    'user:id,first_name,last_name,email',
                    'department:id,name',
                ])
                ->latest('id')
                ->take(5)
                ->get(['id', 'user_id', 'department_id', 'student_id', 'level', 'status', 'enrollment_date']);

            return [
                'stats' => $stats,
                'enrollment_trends' => $enrollmentTrends,
                'students_by_department' => $studentsByDepartment,
                'students_by_level' => $studentsByLevel,
                'recent_students' => $recentStudents,
            ];
        }));
    }

    public function studentStats(Request $request)
    {
        $user = $request->user();
        $student = $user->student;

        if (!$student) {
            return $this->error('Student profile not found', 404);
        }

        // Cache short-lived dashboard payload to avoid recomputing on every refresh.
        // Keep TTL low so numbers feel "live" but performance is stable.
        $cacheKey = "dashboard:student:{$student->id}";

        $payload = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($student) {
            // Counts & sums via aggregates (no need to load full enrollments / attendance rows)
            $enrolledCourses = (int) $student->enrollments()
                ->where('status', 'enrolled')
                ->count();

            $creditsRow = DB::table('enrollments')
                ->join('classes', 'enrollments.class_id', '=', 'classes.id')
                ->join('courses', 'classes.course_id', '=', 'courses.id')
                ->where('enrollments.student_id', $student->id)
                ->where('enrollments.status', 'enrolled')
                ->selectRaw('COALESCE(SUM(courses.credits), 0) AS total_credits')
                ->first();

            $attendanceRow = DB::table('attendance')
                ->join('enrollments', 'attendance.enrollment_id', '=', 'enrollments.id')
                ->where('enrollments.student_id', $student->id)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw("COUNT(*) FILTER (WHERE attendance.status IN ('present','late')) AS present_like")
                ->first();

            $attendanceRate = ((int) ($attendanceRow->total ?? 0)) > 0
                ? round((((int) ($attendanceRow->present_like ?? 0)) / ((int) $attendanceRow->total)) * 100, 2)
                : 0;

            // Fees summary via aggregates (still load feeType list only if you need to render details)
            $fees = $student->fees()
                ->with('feeType')
                ->get();

            $pendingFees = (float) $fees
                ->where('status', '!=', 'paid')
                ->sum(fn ($f) => (float) ($f->amount - $f->paid_amount));

            // Installment-aware next-due computation.
            // For fees with a payment plan, find the first unpaid installment instead of
            // using the fee-level due_date (which reflects the whole fee, not the next tranche).
            $nextDueDate   = null;
            $nextDueAmount = null;

            foreach ($fees->where('status', '!=', 'paid') as $f) {
                $plan = $f->installment_plan; // cast: array|null

                if (!empty($plan['installments'])) {
                    $basePaid  = (float) ($plan['base_paid_amount'] ?? $f->paid_amount ?? 0);
                    $remaining = max(0.0, (float) $f->paid_amount - $basePaid);

                    foreach ($plan['installments'] as $inst) {
                        $instAmt = (float) $inst['amount'];
                        if ($remaining >= $instAmt) {
                            $remaining -= $instAmt;
                            continue;
                        }
                        // First unpaid (or partially-paid) installment
                        $instDue = !empty($inst['due_date'])
                            ? \Carbon\Carbon::parse($inst['due_date'])
                            : null;
                        if ($instDue && ($nextDueDate === null || $instDue->lt($nextDueDate))) {
                            $nextDueDate   = $instDue;
                            $nextDueAmount = round($instAmt - $remaining, 2);
                        }
                        break;
                    }
                } else {
                    // Regular fee without a plan
                    if ($f->due_date && ($nextDueDate === null || $f->due_date->lt($nextDueDate))) {
                        $nextDueDate   = $f->due_date;
                        $nextDueAmount = round((float) ($f->amount - $f->paid_amount), 2);
                    }
                }
            }

            $daysUntilDue = $nextDueDate
                ? (int) now()->startOfDay()->diffInDays($nextDueDate->startOfDay(), false)
                : null;

            // Course list: keep it small for the dashboard (avoid returning huge payloads)
            $courses = $student->enrollments()
                ->with([
                    'class:id,course_id,teacher_id,section,room,academic_year,semester',
                    'class.course:id,code,name,credits,semester,level',
                    'class.teacher:id,user_id',
                    'class.teacher.user:id,first_name,last_name,email',
                ])
                ->where('status', 'enrolled')
                ->latest('id')
                ->take(12)
                ->get();

            return [
                'enrolled_courses' => $enrolledCourses,
                'attendance_rate' => $attendanceRate,
                'pending_fees' => $pendingFees,
                'total_credits' => (int) ($creditsRow->total_credits ?? 0),
                'courses' => $courses,
                'fees_summary' => [
                    'total'           => (float) $fees->sum('amount'),
                    'paid'            => (float) $fees->sum('paid_amount'),
                    'pending'         => (float) $pendingFees,
                    'next_due_date'   => $nextDueDate?->toDateString(),
                    'next_due_amount' => $nextDueAmount,
                    'days_until_due'  => $daysUntilDue,
                ],
            ];
        });

        return $this->success($payload);
    }

    public function teacherStats(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return $this->error('Teacher profile not found', 404);
        }

        $cacheKey = "dashboard:teacher:{$teacher->id}";

        $payload = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($teacher) {
            // Avoid loading all enrollments; use withCount for enrolled students per class.
            $classes = $teacher->classes()
                ->where('is_active', true)
                ->with(['course:id,code,name,credits,semester,level'])
                ->withCount(['enrollments as enrolled_students_count' => function ($q) {
                    $q->where('status', 'enrolled');
                }])
                ->orderByDesc('id')
                ->take(20)
                ->get(['id', 'course_id', 'teacher_id', 'section', 'room', 'academic_year', 'semester', 'capacity', 'is_active']);

            $totalStudents = (int) $classes->sum('enrolled_students_count');

            return [
                'total_classes' => (int) $classes->count(),
                'total_students' => $totalStudents,
                'classes' => $classes,
            ];
        });

        return $this->success($payload);
    }

    public function financeStats()
    {
        return $this->success(Cache::remember('dashboard:finance', now()->addSeconds(45), function () {
            // 4 separate SUM queries → 1 query with conditional aggregation
            $payRow = DB::selectOne("
                SELECT
                    COALESCE(SUM(amount), 0)                                                              AS total_revenue,
                    COALESCE(SUM(amount) FILTER (WHERE payment_date::date = CURRENT_DATE), 0)             AS today_collection,
                    COALESCE(SUM(amount) FILTER (
                        WHERE DATE_TRUNC('month', payment_date) = DATE_TRUNC('month', NOW())
                    ), 0)                                                                                  AS monthly_revenue
                FROM payments
            ");

            $pendingFees = StudentFee::where('status', '!=', 'paid')
                ->sum(DB::raw('amount - paid_amount'));

            // Revenue by fee type
            $revenueByType = DB::table('payments')
                ->join('student_fees', 'payments.student_fee_id', '=', 'student_fees.id')
                ->join('fee_types', 'student_fees.fee_type_id', '=', 'fee_types.id')
                ->selectRaw('fee_types.name, SUM(payments.amount) as total')
                ->groupBy('fee_types.id', 'fee_types.name')
                ->get();

            // Monthly trends
            $monthlyTrends = Payment::selectRaw("TO_CHAR(payment_date, 'YYYY-MM') as month, SUM(amount) as total")
                ->where('payment_date', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Recent payments (limit columns & relations)
            $recentPayments = Payment::with([
                    'studentFee:id,student_id,fee_type_id,amount,paid_amount,status',
                    'studentFee.feeType:id,name',
                    'studentFee.student:id,user_id,student_id,level,status',
                    'studentFee.student.user:id,first_name,last_name,email',
                    'receivedBy:id,first_name,last_name,email',
                ])
                ->latest('id')
                ->take(10)
                ->get(['id', 'student_fee_id', 'amount', 'payment_date', 'reference_number', 'received_by']);

            return [
                'total_revenue'   => (float) ($payRow->total_revenue   ?? 0),
                'pending_fees'    => (float) $pendingFees,
                'today_collection'=> (float) ($payRow->today_collection ?? 0),
                'monthly_revenue' => (float) ($payRow->monthly_revenue  ?? 0),
                // legacy aliases kept for frontend compatibility
                'today_payments'  => (float) ($payRow->today_collection ?? 0),
                'revenue_by_type' => $revenueByType,
                'monthly_trends'  => $monthlyTrends,
                'recent_payments' => $recentPayments,
            ];
        }));
    }

    public function registrarStats()
    {
        return $this->success(Cache::remember('dashboard:registrar', now()->addSeconds(45), function () {
            // 4 separate COUNTs → 1 query
            $row = DB::selectOne("
                SELECT
                    COUNT(*)                                                              AS total_students,
                    COUNT(*) FILTER (WHERE status = 'active')                            AS active_students,
                    COUNT(*) FILTER (
                        WHERE DATE_TRUNC('month', enrollment_date) = DATE_TRUNC('month', NOW())
                    )                                                                     AS new_this_month
                FROM students
            ");
            $totalTeachers = Teacher::count();

            $stats = [
                'total_students'  => (int) ($row->total_students  ?? 0),
                'active_students' => (int) ($row->active_students ?? 0),
                'new_this_month'  => (int) ($row->new_this_month  ?? 0),
                'total_teachers'  => (int) $totalTeachers,
            ];

            // Students by level
            $studentsByLevel = Student::selectRaw('level, COUNT(*) as count')
                ->groupBy('level')
                ->get();

            // Recent registrations (limit columns)
            $recentStudents = Student::with([
                    'user:id,first_name,last_name,email',
                    'department:id,name',
                ])
                ->latest('id')
                ->take(10)
                ->get(['id', 'user_id', 'department_id', 'student_id', 'level', 'status', 'enrollment_date']);

            return [
                'stats' => $stats,
                'students_by_level' => $studentsByLevel,
                'recent_students' => $recentStudents,
            ];
        }));
    }
}
