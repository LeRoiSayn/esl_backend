<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use App\Models\GradeModification;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\CourseEquivalence;
use App\Models\User;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Models\Transaction;
use App\Models\Teacher;
use App\Models\Department;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ==================== STUDENT SEARCH & MANAGEMENT ====================

    /**
     * Search students with advanced filters
     */
    public function searchStudents(Request $request)
    {
        $query = Student::with(['user', 'department.faculty']);

        // Search by name, email, or student_id
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json($students);
    }

    /**
     * Get detailed student information with a full academic transcript view.
     *
     * Returns courses organised by Year (level) → Semester → Course, so the
     * admin sees exactly what the student did each semester, what was validated,
     * what is in progress right now, and what is still to come.
     */
    public function getStudentDetails($id)
    {
        $student = Student::with([
            'user',
            'department.faculty',
            'enrollments.class.course',
            'enrollments.class.teacher.user',
            'enrollments.grades',
            'enrollments.attendance',
            'fees.feeType',
            'fees.payments',
        ])->findOrFail($id);

        // Determine current academic period
        $month = (int) date('n');
        $currentCalendarSemester = $month >= 9 ? '1' : ($month <= 4 ? '2' : '3');

        // Full programme: all active courses for the student's department
        $allProgrammeCourses = Course::where('department_id', $student->department_id)
            ->where('is_active', true)
            ->orderByRaw("CASE level WHEN 'L1' THEN 1 WHEN 'L2' THEN 2 WHEN 'L3' THEN 3 WHEN 'M1' THEN 4 WHEN 'M2' THEN 5 WHEN 'D1' THEN 6 WHEN 'D2' THEN 7 WHEN 'D3' THEN 8 ELSE 9 END")
            ->orderBy('semester')
            ->orderBy('name')
            ->get();

        // Build a lookup: course_id → enrollment (with its latest grade)
        $enrolledCourseMap = [];
        foreach ($student->enrollments as $enrollment) {
            $courseId = $enrollment->class->course_id ?? null;
            if ($courseId) {
                $enrollment->latest_grade = $enrollment->grades->sortByDesc('created_at')->first();
                $enrolledCourseMap[$courseId] = $enrollment;
            }
        }

        // Level (year) labels
        $levelLabels = [
            'L1' => '1ère Année', 'L2' => '2ème Année', 'L3' => '3ème Année',
            'M1' => 'Master 1',   'M2' => 'Master 2',
            'D1' => 'Doctorat 1', 'D2' => 'Doctorat 2', 'D3' => 'Doctorat 3',
        ];

        // Level ordering so we can determine "is future year"
        $levelOrder = ['L1' => 1, 'L2' => 2, 'L3' => 3, 'M1' => 4, 'M2' => 5, 'D1' => 6, 'D2' => 7, 'D3' => 8];
        $studentLevelOrder = $levelOrder[$student->level] ?? 1;

        $totalCreditsEarned = 0;
        $totalCreditsTotal  = $allProgrammeCourses->sum('credits');

        $years = [];

        foreach ($allProgrammeCourses->groupBy('level') as $level => $levelCourses) {
            $thisLevelOrder = $levelOrder[$level] ?? 1;
            $isCurrentYear  = ($level === $student->level);
            $isPastYear     = ($thisLevelOrder < $studentLevelOrder);
            $isFutureYear   = ($thisLevelOrder > $studentLevelOrder);

            $semesters = [];
            $yearCreditsEarned = 0;
            $yearCreditsTotal  = 0;
            $yearValidated = 0;
            $yearInProgress = 0;
            $yearFailed = 0;

            foreach ($levelCourses->groupBy('semester') as $semester => $semesterCourses) {
                $courses        = [];
                $semCreditsEarned = 0;
                $semCreditsTotal  = 0;
                $semValidated   = 0;
                $semInProgress  = 0;
                $semFailed      = 0;
                $semNotEnrolled = 0;

                foreach ($semesterCourses as $course) {
                    $semCreditsTotal += $course->credits;

                    if (isset($enrolledCourseMap[$course->id])) {
                        $enrollment = $enrolledCourseMap[$course->id];
                        $grade      = $enrollment->latest_grade;
                        $finalGrade = $grade ? (float) $grade->final_grade : null;

                        // A course is "validated" if its enrollment is completed with passing grade,
                        // OR if it has a grade >= 50 (teacher has graded it, regardless of enrollment status).
                        if ($finalGrade !== null && $finalGrade >= 50) {
                            $courseStatus = 'validated';
                            $semValidated++;
                            $semCreditsEarned += $course->credits;
                        } elseif ($finalGrade !== null && $finalGrade < 50) {
                            $courseStatus = 'failed';
                            $semFailed++;
                        } else {
                            $courseStatus = 'enrolled'; // In progress, not yet graded
                            $semInProgress++;
                        }
                    } else {
                        $courseStatus   = 'not_enrolled';
                        $semNotEnrolled++;
                        $grade          = null;
                        $enrollment     = null;
                    }

                    $courses[] = [
                        'course'        => $course,
                        'course_status' => $courseStatus,
                        'enrollment'    => $enrollment ? [
                            'id'             => $enrollment->id,
                            'status'         => $enrollment->status,
                            'enrollment_date'=> $enrollment->enrollment_date,
                            'teacher'        => $enrollment->class->teacher ? [
                                'id'         => $enrollment->class->teacher->id,
                                'first_name' => $enrollment->class->teacher->user->first_name ?? '',
                                'last_name'  => $enrollment->class->teacher->user->last_name  ?? '',
                            ] : null,
                        ] : null,
                        'grade'         => $grade ? [
                            'id'                    => $grade->id,
                            'continuous_assessment' => $grade->continuous_assessment,
                            'exam_score'            => $grade->exam_score,
                            'final_grade'           => $grade->final_grade,
                            'letter_grade'          => $grade->letter_grade,
                            'remarks'               => $grade->remarks,
                            'graded_at'             => $grade->graded_at,
                        ] : null,
                    ];
                }

                // Semester status: past (all done), current (some in progress), future (none touched)
                if ($semInProgress > 0) {
                    $semStatus = 'current';
                } elseif ($semValidated > 0 || $semFailed > 0) {
                    $semStatus = 'past';
                } elseif ($isCurrentYear && $semester === $currentCalendarSemester) {
                    $semStatus = 'current';
                } elseif ($isPastYear || ($isCurrentYear && (int)$semester < (int)$currentCalendarSemester)) {
                    $semStatus = 'past';
                } else {
                    $semStatus = 'future';
                }

                $yearCreditsEarned += $semCreditsEarned;
                $yearCreditsTotal  += $semCreditsTotal;
                $yearValidated     += $semValidated;
                $yearInProgress    += $semInProgress;
                $yearFailed        += $semFailed;

                $semesters[] = [
                    'semester'      => $semester,
                    'label'         => "Semestre {$semester}",
                    'status'        => $semStatus,
                    'courses'       => $courses,
                    'stats'         => [
                        'total'         => count($courses),
                        'validated'     => $semValidated,
                        'in_progress'   => $semInProgress,
                        'failed'        => $semFailed,
                        'not_enrolled'  => $semNotEnrolled,
                        'credits_earned'=> $semCreditsEarned,
                        'credits_total' => $semCreditsTotal,
                    ],
                ];
            }

            $totalCreditsEarned += $yearCreditsEarned;

            $years[] = [
                'year'            => $level,
                'year_label'      => $levelLabels[$level] ?? $level,
                'is_current_year' => $isCurrentYear,
                'is_past_year'    => $isPastYear,
                'is_future_year'  => $isFutureYear,
                'semesters'       => $semesters,
                'year_stats'      => [
                    'total'          => $levelCourses->count(),
                    'validated'      => $yearValidated,
                    'in_progress'    => $yearInProgress,
                    'failed'         => $yearFailed,
                    'credits_earned' => $yearCreditsEarned,
                    'credits_total'  => $yearCreditsTotal,
                    'is_year_passed' => ($yearValidated + $yearFailed >= $levelCourses->count()) && $yearInProgress === 0,
                ],
            ];
        }

        // Global statistics
        $allGrades = $student->enrollments->flatMap(function ($enrollment) {
            return $enrollment->grades->map(function ($grade) use ($enrollment) {
                $grade->course = $enrollment->class->course ?? null;
                return $grade;
            });
        });

        $allAttendance     = $student->enrollments->flatMap->attendance;
        $totalAttendance   = $allAttendance->count();
        $presentCount      = $allAttendance->whereIn('status', ['present', 'late'])->count();
        $attendanceRate    = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100, 1) : 100;

        $totalFees = $student->fees->sum('amount');
        $totalPaid = $student->fees->sum('paid_amount');

        $gradedGrades  = $allGrades->filter(fn($g) => $g->final_grade !== null);
        $overallAverage = $gradedGrades->count() > 0 ? round($gradedGrades->avg('final_grade'), 2) : null;

        // Resolve retake course IDs → actual course names for the UI
        $retakeCourseIds     = $student->retake_courses ?? [];
        $retakeCourseDetails = Course::whereIn('id', $retakeCourseIds)
            ->get(['id', 'name', 'code', 'level'])
            ->toArray();

        $feesDetail = $student->fees->map(fn($f) => [
            'fee_type'      => $f->feeType ? ['name' => $f->feeType->name, 'category' => $f->feeType->category] : null,
            'amount'        => $f->amount,
            'paid_amount'   => $f->paid_amount,
            'balance'       => (float)$f->amount - (float)$f->paid_amount,
            'status'        => $f->status,
            'due_date'      => $f->due_date,
            'academic_year' => $f->academic_year,
            'payments'      => $f->payments->sortByDesc('payment_date')->values()->map(fn($p) => [
                'payment_date'     => $p->payment_date,
                'amount'           => $p->amount,
                'payment_method'   => $p->payment_method,
                'reference_number' => $p->reference_number,
                'fee_type_name'    => $f->feeType?->name,
            ])->toArray(),
        ])->toArray();

        return response()->json([
            'student' => $student,
            'retake_course_details' => $retakeCourseDetails,
            'fees_detail' => $feesDetail,
            'academic_progress' => [
                'current_level'     => $student->level,
                'current_semester'  => $currentCalendarSemester,
                'years'             => $years,
                'programme_summary' => [
                    'total_programme_courses' => $allProgrammeCourses->count(),
                    'credits_earned'          => $totalCreditsEarned,
                    'credits_total'           => $totalCreditsTotal,
                    'completion_percentage'   => $totalCreditsTotal > 0
                        ? round(($totalCreditsEarned / $totalCreditsTotal) * 100, 1)
                        : 0,
                ],
            ],
            'statistics' => [
                'overall_average' => $overallAverage,
                'total_credits'   => $totalCreditsEarned,
                'financial'       => [
                    'total_fees' => $totalFees,
                    'paid'       => $totalPaid,
                    'remaining'  => $totalFees - $totalPaid,
                ],
                'attendance_rate' => $attendanceRate,
            ],
        ]);
    }

    /**
     * Admin report (print-friendly data):
     * - Academic curriculum report: all programme courses (including not yet taken)
     * - Financial report: fees & payments grouped by academic year
     */
    public function getStudentReport(int $id)
    {
        $payload = $this->buildStudentReportPayload($id);
        return $this->success($payload);
    }

    private function academicYearFromDate($date): string
    {
        $d = $date ? \Carbon\Carbon::parse($date) : now();
        // Academic year starts in September
        $year = (int) $d->format('Y');
        $month = (int) $d->format('n');
        return $month >= 9 ? "{$year}-" . ($year + 1) : ($year - 1) . "-{$year}";
    }

    /**
     * Build the payload used by both:
     * - JSON preview (getStudentReport)
     * - printable/downloadable sheet (downloadStudentReportHtml)
     */
    private function buildStudentReportPayload(int $id): array
    {
        $student = Student::with([
            'user',
            'department.faculty',
            'enrollments.class.course',
            'enrollments.grades',
            'fees.feeType',
            'fees.payments',
        ])->findOrFail($id);

        // -------------------- Academic (curriculum coverage) --------------------
        $allProgrammeCourses = Course::where('department_id', $student->department_id)
            ->where('is_active', true)
            ->orderByRaw("CASE level WHEN 'L1' THEN 1 WHEN 'L2' THEN 2 WHEN 'L3' THEN 3 WHEN 'M1' THEN 4 WHEN 'M2' THEN 5 WHEN 'D1' THEN 6 WHEN 'D2' THEN 7 WHEN 'D3' THEN 8 ELSE 9 END")
            ->orderBy('semester')
            ->orderBy('name')
            ->get();

        $enrolledCourseMap = [];
        foreach ($student->enrollments as $enrollment) {
            $courseId = $enrollment->class->course_id ?? null;
            if (!$courseId) continue;
            $enrollment->latest_grade = $enrollment->grades->sortByDesc('created_at')->first();
            $enrolledCourseMap[$courseId] = $enrollment;
        }

        $curriculum = [];
        foreach ($allProgrammeCourses as $course) {
            $enrollment = $enrolledCourseMap[$course->id] ?? null;
            $grade = $enrollment?->latest_grade;
            $finalGrade = $grade ? (float) $grade->final_grade : null;

            if ($finalGrade !== null && $finalGrade >= 50) {
                $status = 'passed';
            } elseif ($finalGrade !== null && $finalGrade < 50) {
                $status = 'failed';
            } elseif ($enrollment) {
                $status = 'in_progress';
            } else {
                $status = 'not_taken';
            }

            $curriculum[] = [
                'course_id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'credits' => $course->credits,
                'level' => $course->level,
                'semester' => $course->semester,
                'course_type' => $course->course_type ?? null,
                'status' => $status,
                'grade' => $grade ? [
                    'final_grade' => $grade->final_grade,
                    'letter_grade' => $grade->letter_grade,
                    'validated_at' => $grade->validated_at ?? null,
                ] : null,
            ];
        }

        // -------------------- Finance (by academic year) --------------------
        $fees = StudentFee::with(['feeType', 'payments'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year')
            ->orderBy('due_date')
            ->get();

        $completedTransactions = Transaction::where('student_id', $student->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $transactionsByYear = [];
        foreach ($completedTransactions as $tx) {
            $year = $this->academicYearFromDate($tx->created_at);
            $transactionsByYear[$year][] = [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'payment_method' => $tx->payment_method,
                'created_at' => $tx->created_at?->toIso8601String(),
            ];
        }

        $years = [];
        foreach ($fees->groupBy('academic_year') as $academicYear => $yearFees) {
            $total = (float) $yearFees->sum('amount');
            $paid = (float) $yearFees->sum('paid_amount');
            $balance = max(0, $total - $paid);

            $years[] = [
                'academic_year' => $academicYear,
                'summary' => [
                    'total' => $total,
                    'paid' => $paid,
                    'balance' => $balance,
                ],
                'fees' => $yearFees->map(function ($fee) {
                    $amount = (float) $fee->amount;
                    $paid = (float) $fee->paid_amount;
                    return [
                        'id' => $fee->id,
                        'fee_type' => $fee->feeType?->name,
                        'amount' => $amount,
                        'paid' => $paid,
                        'balance' => max(0, $amount - $paid),
                        'due_date' => $fee->due_date?->format('Y-m-d'),
                        'status' => $fee->status,
                        'payments' => $fee->payments?->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'reference_number' => $p->reference_number,
                                'amount' => (float) $p->amount,
                                'payment_method' => $p->payment_method,
                                'payment_date' => $p->payment_date?->format('Y-m-d'),
                            ];
                        })->values() ?? [],
                    ];
                })->values(),
                'transactions' => $transactionsByYear[$academicYear] ?? [],
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->user?->full_name ?? trim(($student->user?->first_name ?? '') . ' ' . ($student->user?->last_name ?? '')),
                'email' => $student->user?->email,
                'department' => $student->department?->name,
                'faculty' => $student->department?->faculty?->name,
                'level' => $student->level,
                'current_semester' => $student->current_semester ?? null,
                'status' => $student->status,
            ],
            'academic' => [
                'programme_courses_count' => $allProgrammeCourses->count(),
                'curriculum' => $curriculum,
            ],
            'finance' => [
                'years' => $years,
            ],
        ];
    }

    // ==================== STUDENT REPORT SHEETS (2 separate files) ====================

    public function viewStudentAcademicSheet(int $id)
    {
        return $this->viewSheet($id, 'academic');
    }

    public function downloadStudentAcademicSheet(int $id)
    {
        return $this->downloadSheet($id, 'academic');
    }

    public function viewStudentFinancialSheet(int $id)
    {
        return $this->viewSheet($id, 'financial');
    }

    public function downloadStudentFinancialSheet(int $id)
    {
        return $this->downloadSheet($id, 'financial');
    }

    private function viewSheet(int $id, string $type)
    {
        $html = $this->renderStudentSheetHtml($id, $type);
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function downloadSheet(int $id, string $type)
    {
        $payload = $this->buildStudentReportPayload($id);
        $html = $this->renderStudentSheetHtml($id, $type);
        $sid = $payload['student']['student_id'] ?? $id;
        $filename = "student-{$type}-sheet-{$sid}.html";

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function renderStudentSheetHtml(int $id, string $type): string
    {
        $payload = $this->buildStudentReportPayload($id);

        $logoDataUri = null;
        $logoPath = public_path('esl-logo.png');
        if (file_exists($logoPath)) {
            $logoDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $universityName = config('app.name', 'École de Santé de Libreville');

        $view = $type === 'financial'
            ? 'reports.student_financial_sheet'
            : 'reports.student_academic_sheet';

        return view($view, [
            'payload' => $payload,
            'logoDataUri' => $logoDataUri,
            'universityName' => $universityName,
        ])->render();
    }

    // ==================== GRADE MANAGEMENT ====================

    /**
     * Update a student's grade with audit log
     */
    public function updateGrade(Request $request, $gradeId)
    {
        $request->validate([
            'grade' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|min:10|max:500',
        ]);

        $grade = Grade::findOrFail($gradeId);
        $oldValue = $grade->final_grade;
        $newValue = $request->grade;

        // Don't update if same value
        if ($oldValue == $newValue) {
            return response()->json([
                'message' => 'La note est déjà à cette valeur',
            ]);
        }

        // Create modification log
        GradeModification::create([
            'grade_id' => $gradeId,
            'modified_by' => $request->user()->id,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $request->reason,
            'ip_address' => $request->ip(),
        ]);

        // Update grade using the same formula as Grade model
        $grade->final_grade = $newValue;
        $grade->letter_grade = Grade::calculateLetterGrade((float) $newValue);
        $grade->save();

        return response()->json([
            'message' => 'Note mise à jour avec succès',
            'grade' => $grade,
        ]);
    }

    /**
     * Get grade modification history
     */
    public function getGradeHistory($gradeId)
    {
        $modifications = GradeModification::with('modifier')
            ->where('grade_id', $gradeId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['modifications' => $modifications]);
    }

    // ==================== COURSE MANAGEMENT (Transferred Students) ====================

    /**
     * Get student's enrolled courses
     */
    public function getStudentCourses($studentId)
    {
        $enrollments = Enrollment::with('class.course')
            ->where('student_id', $studentId)
            ->get();

        $equivalences = CourseEquivalence::with('equivalentCourse')
            ->where('student_id', $studentId)
            ->get();

        return response()->json([
            'enrollments' => $enrollments,
            'equivalences' => $equivalences,
        ]);
    }

    /**
     * Add a course to student's enrollment
     */
    public function addStudentCourse(Request $request, $studentId)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $month = (int) date('n');
        $academicYear = $month >= 9 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');
        $course = Course::findOrFail($request->course_id);
        $courseSemester = $course->semester ?? '1';

        // Find or create a class for this course
        $class = \App\Models\ClassModel::firstOrCreate(
            [
                'course_id' => $request->course_id,
                'academic_year' => $academicYear,
                'semester' => $courseSemester,
                'section' => 'A',
            ],
            [
                'room' => 'TBD',
                'capacity' => 50,
                'is_active' => true,
            ]
        );

        // Check if already enrolled in this class
        $exists = Enrollment::where('student_id', $studentId)
            ->where('class_id', $class->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'L\'étudiant est déjà inscrit à ce cours',
            ], 422);
        }

        $enrollment = Enrollment::create([
            'student_id' => $studentId,
            'class_id' => $class->id,
            'enrollment_date' => now(),
            'status' => 'enrolled',
        ]);

        return response()->json([
            'message' => 'Cours ajouté avec succès',
            'enrollment' => $enrollment->load('class.course'),
        ], 201);
    }

    /**
     * Remove a course from student's enrollment
     */
    public function removeStudentCourse(Request $request, $studentId, $courseId)
    {
        // Find enrollment through class → course
        $enrollment = Enrollment::where('student_id', $studentId)
            ->whereHas('class', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            })
            ->firstOrFail();

        $enrollment->delete();

        return response()->json([
            'message' => 'Cours retiré avec succès',
        ]);
    }

    /**
     * Add course equivalence for transferred student
     */
    public function addCourseEquivalence(Request $request, $studentId)
    {
        $request->validate([
            'original_course_name' => 'required|string|max:255',
            'original_institution' => 'required|string|max:255',
            'equivalent_course_id' => 'nullable|exists:courses,id',
            'original_grade' => 'nullable|numeric|min:0|max:20',
            'original_credits' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $equivalence = CourseEquivalence::create([
            'student_id' => $studentId,
            'original_course_name' => $request->original_course_name,
            'original_institution' => $request->original_institution,
            'equivalent_course_id' => $request->equivalent_course_id,
            'original_grade' => $request->original_grade,
            'original_credits' => $request->original_credits,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Équivalence ajoutée avec succès',
            'equivalence' => $equivalence,
        ], 201);
    }

    /**
     * Review course equivalence
     */
    public function reviewEquivalence(Request $request, $equivalenceId)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'equivalent_course_id' => 'required_if:status,approved|nullable|exists:courses,id',
            'notes' => 'nullable|string',
        ]);

        $equivalence = CourseEquivalence::findOrFail($equivalenceId);
        
        $equivalence->update([
            'status' => $request->status,
            'equivalent_course_id' => $request->equivalent_course_id,
            'reviewed_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        // If approved, auto-enroll in equivalent course
        if ($request->status === 'approved' && $request->equivalent_course_id) {
            Enrollment::firstOrCreate([
                'student_id' => $equivalence->student_id,
                'course_id' => $request->equivalent_course_id,
            ], [
                'enrollment_date' => now(),
                'status' => 'active',
            ]);

            // Create grade if original grade provided
            if ($equivalence->original_grade) {
                Grade::firstOrCreate([
                    'student_id' => $equivalence->student_id,
                    'course_id' => $request->equivalent_course_id,
                ], [
                    'grade' => $equivalence->original_grade,
                    'grade_type' => 'equivalence',
                    'semester' => 'EQ', // Equivalence
                ]);
            }
        }

        return response()->json([
            'message' => 'Équivalence ' . ($request->status === 'approved' ? 'approuvée' : 'rejetée'),
            'equivalence' => $equivalence->load('equivalentCourse'),
        ]);
    }

    /**
     * Add historical (transfer) grade for a transferred student
     */
    public function addTransferGrade(Request $request, $studentId)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'final_grade'   => 'required|numeric|min:0|max:100',
            'source_school' => 'nullable|string|max:255',
        ]);

        $student = Student::findOrFail($studentId);

        // Check for existing non-transfer enrollment in this course
        $existing = Enrollment::where('student_id', $studentId)
            ->where('status', '!=', 'transfer')
            ->whereHas('class', fn($q) => $q->where('course_id', $request->course_id))
            ->first();

        if ($existing) {
            return response()->json(['message' => 'L\'étudiant est déjà inscrit dans ce cours.'], 422);
        }

        // Find any class for this course, or use first available
        $class = ClassModel::where('course_id', $request->course_id)->first();

        if (!$class) {
            return response()->json(['message' => 'Aucune classe trouvée pour ce cours.'], 422);
        }

        // Create transfer enrollment
        $enrollment = Enrollment::create([
            'student_id'      => $studentId,
            'class_id'        => $class->id,
            'enrollment_date' => now(),
            'status'          => 'transfer',
        ]);

        // Create grade record
        $letterGrade = Grade::calculateLetterGrade((float) $request->final_grade);
        $remarks = $request->source_school
            ? 'Transféré de: ' . $request->source_school
            : 'Cours transféré';

        Grade::create([
            'enrollment_id' => $enrollment->id,
            'final_grade'   => $request->final_grade,
            'letter_grade'  => $letterGrade,
            'remarks'       => $remarks,
            'graded_by'     => $request->user()->id,
            'graded_at'     => now(),
            'validated_at'  => now(),
            'validated_by'  => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Note de transfert ajoutée avec succès',
        ], 201);
    }

    // ==================== ANALYTICS ====================

    /**
     * Get institutional KPIs
     */
    public function getKPIs()
    {
        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'active')->count();
        $totalTeachers = Teacher::count();
        $totalCourses = Course::count();

        // Financial KPIs
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $pendingPayments = Payment::where('status', 'pending')->sum('amount');

        // Academic KPIs
        $averageGrade = Grade::avg('final_grade');
        $passRate = Grade::where('final_grade', '>=', 50)->count() / max(Grade::count(), 1) * 100;

        // Enrollment trends (last 5 years)
        $enrollmentTrends = Student::selectRaw('YEAR(created_at) as year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->limit(5)
            ->get();

        // Department statistics
        $departmentStats = Department::withCount('students')
            ->with(['students.enrollments.grades'])
            ->get()
            ->map(function ($dept) {
                $grades = $dept->students->flatMap(function ($student) {
                    return $student->enrollments->flatMap->grades;
                });
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'student_count' => $dept->students_count,
                    'average_grade' => round($grades->avg('final_grade') ?? 0, 2),
                    'pass_rate' => $grades->count() > 0
                        ? round($grades->where('final_grade', '>=', 50)->count() / $grades->count() * 100, 1)
                        : 0,
                ];
            });

        return response()->json([
            'overview' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'total_teachers' => $totalTeachers,
                'total_courses' => $totalCourses,
            ],
            'financial' => [
                'total_revenue' => $totalRevenue,
                'pending_payments' => $pendingPayments,
                'collection_rate' => $totalRevenue > 0 
                    ? round($totalRevenue / ($totalRevenue + $pendingPayments) * 100, 1)
                    : 0,
            ],
            'academic' => [
                'average_grade' => round($averageGrade ?? 0, 2),
                'pass_rate' => round($passRate, 1),
            ],
            'trends' => [
                'enrollment' => $enrollmentTrends,
            ],
            'departments' => $departmentStats,
        ]);
    }

    /**
     * Get students with alerts (late payments, low grades, etc.)
     */
    public function getStudentAlerts()
    {
        // Students with outstanding fees
        $paymentDelays = Student::with(['user', 'fees'])
            ->whereHas('fees')
            ->limit(20)
            ->get()
            ->map(function ($student) {
                $totalFees = $student->fees->sum('amount');
                $totalPaid = $student->fees->sum('paid_amount');
                $owed = $totalFees - $totalPaid;
                return [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->user->first_name . ' ' . $student->user->last_name,
                        'student_id' => $student->student_id,
                    ],
                    'amount_owed' => $owed,
                    'type' => 'payment_delay',
                ];
            })
            ->filter(fn($item) => $item['amount_owed'] > 0)
            ->values();

        // Students with low grades (through enrollments)
        $lowGrades = Student::with(['user', 'enrollments.grades'])
            ->whereHas('enrollments.grades')
            ->get()
            ->map(function ($student) {
                $grades = $student->enrollments->flatMap->grades;
                $avg = $grades->avg('final_grade');
                return [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->user->first_name . ' ' . $student->user->last_name,
                        'student_id' => $student->student_id,
                    ],
                    'average' => round($avg ?? 0, 2),
                    'type' => 'low_grades',
                ];
            })
            ->filter(fn($item) => $item['average'] > 0 && $item['average'] < 50)
            ->values()
            ->take(20);

        return response()->json([
            'payment_delays' => $paymentDelays,
            'low_grades' => $lowGrades,
        ]);
    }
}
