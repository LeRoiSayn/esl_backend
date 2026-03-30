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

class DashboardController extends Controller
{
    public function adminStats()
    {
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'total_courses' => Course::count(),
            'total_departments' => Department::count(),
            'total_faculties' => Faculty::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'active_teachers' => Teacher::where('status', 'active')->count(),
        ];

        // Enrollment trends (last 6 months)
        $enrollmentTrends = Student::selectRaw('DATE_FORMAT(enrollment_date, "%Y-%m") as month, COUNT(*) as count')
            ->where('enrollment_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Students by department
        $studentsByDepartment = Department::withCount('students')
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => ['name' => $d->name, 'count' => $d->students_count]);

        // Students by level
        $studentsByLevel = Student::selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->get();

        // Recent students
        $recentStudents = Student::with(['user', 'department'])
            ->latest()
            ->take(5)
            ->get();

        return $this->success([
            'stats' => $stats,
            'enrollment_trends' => $enrollmentTrends,
            'students_by_department' => $studentsByDepartment,
            'students_by_level' => $studentsByLevel,
            'recent_students' => $recentStudents,
        ]);
    }

    public function studentStats(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return $this->error('Student profile not found', 404);
        }

        $enrollments = $student->enrollments()
            ->with(['class.course', 'class.teacher.user'])
            ->where('status', 'enrolled')
            ->get();

        $attendanceRate = $student->attendance_rate;

        $fees = $student->fees()
            ->with('feeType')
            ->get();

        $pendingFees = $fees->where('status', '!=', 'paid')->sum('balance');

        $nextFee = $fees->where('status', '!=', 'paid')
            ->filter(fn($f) => $f->due_date !== null)
            ->sortBy('due_date')
            ->first();
        $daysUntilDue = $nextFee
            ? (int) now()->startOfDay()->diffInDays($nextFee->due_date->startOfDay(), false)
            : null;

        return $this->success([
            'enrolled_courses' => $enrollments->count(),
            'attendance_rate' => $attendanceRate,
            'pending_fees' => $pendingFees,
            'total_credits' => $enrollments->sum(fn($e) => $e->class->course->credits ?? 0),
            'courses' => $enrollments,
            'fees_summary' => [
                'total' => $fees->sum('amount'),
                'paid' => $fees->sum('paid_amount'),
                'pending' => $pendingFees,
                'next_due_date' => $nextFee?->due_date?->toDateString(),
                'days_until_due' => $daysUntilDue,
            ],
        ]);
    }

    public function teacherStats(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return $this->error('Teacher profile not found', 404);
        }

        $classes = $teacher->classes()
            ->with(['course', 'enrollments'])
            ->where('is_active', true)
            ->get();

        $totalStudents = $classes->sum(fn($c) => $c->enrollments->where('status', 'enrolled')->count());

        return $this->success([
            'total_classes' => $classes->count(),
            'total_students' => $totalStudents,
            'classes' => $classes,
        ]);
    }

    public function financeStats()
    {
        $totalRevenue = Payment::sum('amount');
        $pendingFees = StudentFee::where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount'));
        $todayPayments = Payment::whereDate('payment_date', today())->sum('amount');
        $monthlyRevenue = Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        // Revenue by fee type
        $revenueByType = DB::table('payments')
            ->join('student_fees', 'payments.student_fee_id', '=', 'student_fees.id')
            ->join('fee_types', 'student_fees.fee_type_id', '=', 'fee_types.id')
            ->selectRaw('fee_types.name, SUM(payments.amount) as total')
            ->groupBy('fee_types.id', 'fee_types.name')
            ->get();

        // Monthly trends
        $monthlyTrends = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as month, SUM(amount) as total')
            ->where('payment_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent payments
        $recentPayments = Payment::with(['studentFee.student.user', 'studentFee.feeType', 'receivedBy'])
            ->latest()
            ->take(10)
            ->get();

        return $this->success([
            'total_revenue' => $totalRevenue,
            'pending_fees' => $pendingFees,
            'today_payments' => $todayPayments,
            'monthly_revenue' => $monthlyRevenue,
            'revenue_by_type' => $revenueByType,
            'monthly_trends' => $monthlyTrends,
            'recent_payments' => $recentPayments,
        ]);
    }

    public function registrarStats()
    {
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'new_this_month' => Student::whereMonth('enrollment_date', now()->month)
                ->whereYear('enrollment_date', now()->year)
                ->count(),
        ];

        // Students by level
        $studentsByLevel = Student::selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->get();

        // Recent registrations
        $recentStudents = Student::with(['user', 'department'])
            ->latest()
            ->take(10)
            ->get();

        return $this->success([
            'stats' => $stats,
            'students_by_level' => $studentsByLevel,
            'recent_students' => $recentStudents,
        ]);
    }
}
