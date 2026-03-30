<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\FeeType;
use App\Models\StudentFee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with(['user', 'department.faculty']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                })->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($students);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'department_id' => 'required|exists:departments,id',
            'level' => 'required|in:L1,L2,L3,M1,M2,D1,D2,D3',
            'enrollment_date' => 'required|date',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email',
        ]);

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'role' => 'student',
            ]);

            // Generate student ID
            $lastStudent = Student::orderBy('id', 'desc')->first();
            $studentNumber = $lastStudent ? intval(substr($lastStudent->student_id, 4)) + 1 : 1;
            $studentId = 'STU-' . str_pad($studentNumber, 5, '0', STR_PAD_LEFT);

            // Create student
            $student = Student::create([
                'user_id' => $user->id,
                'department_id' => $request->department_id,
                'student_id' => $studentId,
                'level' => $request->level,
                'enrollment_date' => $request->enrollment_date,
                'guardian_name' => $request->guardian_name,
                'guardian_phone' => $request->guardian_phone,
                'guardian_email' => $request->guardian_email,
            ]);

            // Auto-enroll in courses
            $enrolledCount = $this->autoEnrollStudent($student);

            // Auto-assign applicable fee types for the current academic year
            $this->autoAssignFees($student);

            DB::commit();

            ActivityLog::log('create', "Created student: {$user->full_name} and auto-enrolled in {$enrolledCount} courses", $student);

            return $this->success(
                $student->load(['user', 'department.faculty', 'enrollments.class.course']),
                "Student created and enrolled in {$enrolledCount} courses",
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create student: ' . $e->getMessage(), 500);
        }
    }

    public function show(Student $student)
    {
        $student->load([
            'user',
            'department.faculty',
            'enrollments.class.course',
            'enrollments.class.teacher.user',
            'fees.feeType',
            'fees.payments',
        ]);

        return $this->success($student);
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'department_id' => 'sometimes|exists:departments,id',
            'level' => 'sometimes|in:L1,L2,L3,M1,M2,D1,D2,D3',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email',
            'status' => 'sometimes|in:active,inactive,graduated,suspended',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Update user
            $userFields = $request->only(['first_name', 'last_name', 'phone', 'address', 'date_of_birth', 'gender']);
            if (!empty($request->password)) {
                $userFields['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
            }
            $student->user->update($userFields);

            // Update student
            $student->update($request->only([
                'department_id', 'level', 'guardian_name', 'guardian_phone', 'guardian_email', 'status'
            ]));

            DB::commit();

            ActivityLog::log('update', "Updated student: {$student->user->full_name}", $student);

            return $this->success($student->load(['user', 'department.faculty']), 'Student updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update student: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Student $student)
    {
        $name = $student->user->full_name;
        
        $student->user->delete(); // This will cascade delete the student

        ActivityLog::log('delete', "Deleted student: {$name}");

        return $this->success(null, 'Student deleted successfully');
    }

    public function autoEnroll(Student $student)
    {
        $enrolledCount = $this->autoEnrollStudent($student);

        return $this->success(
            $student->load(['enrollments.class.course']),
            "Student enrolled in {$enrolledCount} courses"
        );
    }

    /**
     * Advance student to the next semester (S1→S2→S3).
     * S3 is the end of the year; promotion to next level is done separately.
     */
    public function advanceSemester(Student $student)
    {
        $current = (string) ($student->current_semester ?? '1');

        if ($current === '3') {
            return $this->error('Le semestre 3 est le dernier semestre de l\'année. Veuillez utiliser la promotion pour passer à l\'année supérieure.', 422);
        }

        $next = (string) ((int) $current + 1);
        $student->update(['current_semester' => $next]);

        return $this->success(
            $student->fresh(['user', 'department']),
            "Étudiant avancé au semestre {$next}"
        );
    }

    private function autoEnrollStudent(Student $student): int
    {
        // Use the student's stored current_semester (set explicitly by admin).
        // Fallback to month-based detection for backward compatibility.
        if (!empty($student->current_semester)) {
            $currentSemester = (string) $student->current_semester;
        } else {
            $month = (int) date('n');
            if ($month >= 9 && $month <= 12) {
                $currentSemester = '1';
            } elseif ($month >= 1 && $month <= 4) {
                $currentSemester = '2';
            } else {
                $currentSemester = '3';
            }
        }

        $month = (int) date('n');
        $academicYear = $month >= 9 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');

        // Get courses for student's department, level AND current semester
        $courses = Course::where('department_id', $student->department_id)
            ->where('level', $student->level)
            ->where('semester', $currentSemester)
            ->where('is_active', true)
            ->get();

        $enrolledCount = 0;

        // Get available teachers from the same department for auto-assignment
        $departmentTeachers = Teacher::where('department_id', $student->department_id)
            ->where('status', 'active')
            ->get();

        foreach ($courses as $course) {
            // Find or create class for this course in current semester
            $class = ClassModel::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'academic_year' => $academicYear,
                    'semester' => $currentSemester,
                    'section' => 'A',
                ],
                [
                    'room' => 'TBD',
                    'capacity' => 50,
                    'is_active' => true,
                ]
            );

            // Auto-assign a teacher if the class has none and teachers are available
            if (!$class->teacher_id && $departmentTeachers->isNotEmpty()) {
                // Pick the teacher with the fewest classes (load balancing)
                $teacher = $departmentTeachers->sortBy(function ($t) {
                    return ClassModel::where('teacher_id', $t->id)->count();
                })->first();

                $class->update(['teacher_id' => $teacher->id]);
            }

            // Check if already enrolled
            $existingEnrollment = Enrollment::where('student_id', $student->id)
                ->where('class_id', $class->id)
                ->first();

            if (!$existingEnrollment) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'enrollment_date' => now(),
                    'status' => 'enrolled',
                ]);
                $enrolledCount++;
            }
        }

        // Enroll in retake courses from previous levels (offered this semester)
        $retakeCourseIds = $student->retake_courses ?? [];
        if (!empty($retakeCourseIds)) {
            $retakeCourses = Course::whereIn('id', $retakeCourseIds)
                ->where('semester', $currentSemester)
                ->where('is_active', true)
                ->get();

            foreach ($retakeCourses as $course) {
                // Skip if the student already has a passing validated grade for this course
                $hasPassed = $student->enrollments()
                    ->whereHas('class', fn($q) => $q->where('course_id', $course->id))
                    ->whereHas('grades', fn($q) => $q->whereNotNull('validated_at')->where('final_grade', '>=', 50))
                    ->exists();

                if ($hasPassed) continue;

                // Skip if already enrolled in this course this semester
                $alreadyEnrolledThisSemester = $student->enrollments()
                    ->whereHas('class', fn($q) => $q
                        ->where('course_id', $course->id)
                        ->where('academic_year', $academicYear)
                        ->where('semester', $currentSemester))
                    ->exists();

                if ($alreadyEnrolledThisSemester) continue;

                $class = ClassModel::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'academic_year' => $academicYear,
                        'semester' => $currentSemester,
                        'section' => 'A',
                    ],
                    [
                        'room' => 'TBD',
                        'capacity' => 50,
                        'is_active' => true,
                    ]
                );

                if (!$class->teacher_id && $departmentTeachers->isNotEmpty()) {
                    $teacher = $departmentTeachers->sortBy(fn($t) => ClassModel::where('teacher_id', $t->id)->count())->first();
                    $class->update(['teacher_id' => $teacher->id]);
                }

                Enrollment::create([
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'enrollment_date' => now(),
                    'status' => 'enrolled',
                ]);
                $enrolledCount++;
            }
        }

        return $enrolledCount;
    }

    /**
     * Auto-assign all applicable active fee types to a student for the current academic year.
     * Fees with level = NULL apply to all students; fees with a specific level only apply
     * to students at that level. Skips a fee type if it is already assigned for the year.
     */
    private function autoAssignFees(Student $student): int
    {
        $month = (int) date('n');
        $academicYear = $month >= 9
            ? date('Y') . '-' . (date('Y') + 1)
            : (date('Y') - 1) . '-' . date('Y');

        $dueDate = $month >= 9
            ? date('Y') . '-12-31'   // Semester 1 → end of December
            : date('Y') . '-06-30';  // Semester 2/3 → end of June

        $feeTypes = FeeType::where('is_active', true)
            ->where(function ($q) use ($student) {
                $q->whereNull('level')
                  ->orWhere('level', $student->level);
            })
            ->get();

        $assigned = 0;
        foreach ($feeTypes as $feeType) {
            $exists = StudentFee::where('student_id', $student->id)
                ->where('fee_type_id', $feeType->id)
                ->where('academic_year', $academicYear)
                ->exists();

            if (!$exists) {
                StudentFee::create([
                    'student_id'    => $student->id,
                    'fee_type_id'   => $feeType->id,
                    'amount'        => $feeType->amount,
                    'due_date'      => $dueDate,
                    'academic_year' => $academicYear,
                    'status'        => 'pending',
                ]);
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Promote a student to the next undergraduate academic level.
     *
     * Rules:
     *   - L1 → L2, L2 → L3  (always, regardless of failed courses)
     *   - L3 → mark status as 'graduated' (undergraduate cycle completed)
     *   - Failed courses are tracked in student.retake_courses
     *   - Master / Doctorate admission must be done via a separate process
     */
    public function promoteToNextLevel(Student $student)
    {
        $progressionMap = ['L1' => 'L2', 'L2' => 'L3'];

        if (!isset($progressionMap[$student->level]) && $student->level !== 'L3') {
            return $this->error(
                'Level progression is only available for undergraduate levels (L1, L2, L3). ' .
                "Current level: {$student->level}.",
                422
            );
        }

        if ($student->status === 'graduated') {
            return $this->error(
                'This student has already completed the undergraduate cycle and cannot be promoted further.',
                422
            );
        }

        DB::beginTransaction();
        try {
            $oldLevel = $student->level;

            // ── Step 1: Identify failed / ungraded courses at the current level ──────
            // A course is a retake candidate if the student has NO passing validated grade for it.
            $levelEnrollments = $student->enrollments()
                ->whereHas('class.course', fn($q) => $q->where('level', $oldLevel))
                ->with('class:id,course_id')
                ->get();

            $levelCourseIds = $levelEnrollments
                ->pluck('class.course_id')
                ->unique();

            $newRetakeCourseIds = $levelCourseIds->filter(function ($courseId) use ($student) {
                // Check for any passing validated grade across all enrollments for this course
                return !$student->enrollments()
                    ->whereHas('class', fn($q) => $q->where('course_id', $courseId))
                    ->whereHas('grades', fn($q) => $q
                        ->whereNotNull('validated_at')
                        ->where('final_grade', '>=', 50))
                    ->exists();
            })->values()->toArray();

            // ── Step 2: Prune previously tracked retakes that have now been passed ──
            $existingRetakes = $student->retake_courses ?? [];
            $remainingRetakes = array_filter($existingRetakes, function ($courseId) use ($student) {
                return !$student->enrollments()
                    ->whereHas('class', fn($q) => $q->where('course_id', $courseId))
                    ->whereHas('grades', fn($q) => $q
                        ->whereNotNull('validated_at')
                        ->where('final_grade', '>=', 50))
                    ->exists();
            });

            // ── Step 3: Merge and deduplicate retake course IDs ──────────────────────
            $updatedRetakes = array_values(array_unique(
                array_merge(array_values($remainingRetakes), $newRetakeCourseIds)
            ));

            // ── Step 4: Mark all current 'enrolled' enrollments as 'completed' ────────
            // This clears the active course list so the admin can assign new-level courses
            $student->enrollments()
                ->where('status', 'enrolled')
                ->update(['status' => 'completed']);

            // ── Step 5: Apply promotion ───────────────────────────────────────────────
            if ($oldLevel === 'L3') {
                // End of undergraduate cycle — mark as graduated
                $student->retake_courses = $updatedRetakes;
                $student->status = 'graduated';
                $student->save();

                ActivityLog::log(
                    'level_promotion',
                    "Student {$student->user->full_name} ({$student->student_id}) completed the undergraduate cycle (L3). " .
                    "Status set to graduated. Retake courses on record: " . count($updatedRetakes) . ".",
                    $student,
                    ['level' => 'L3', 'status' => 'active'],
                    ['level' => 'L3', 'status' => 'graduated', 'retake_courses' => $updatedRetakes]
                );

                DB::commit();

                $message = "Undergraduate cycle completed. {$student->user->full_name} has been marked as graduated.";
                if (count($updatedRetakes) > 0) {
                    $message .= " " . count($updatedRetakes) . " course(s) remain on record as retakes.";
                }

                return $this->success($student->load(['user', 'department']), $message);
            }

            // L1 → L2 or L2 → L3
            $nextLevel = $progressionMap[$oldLevel];
            $student->level = $nextLevel;
            $student->current_semester = '1'; // Reset to semester 1 for new level
            $student->retake_courses = $updatedRetakes;
            $student->save();

            // ── Step 5: Auto-assign all applicable fees for the new level ─────────────
            $feesAssigned = $this->autoAssignFees($student);

            ActivityLog::log(
                'level_promotion',
                "Student {$student->user->full_name} ({$student->student_id}) promoted from {$oldLevel} to {$nextLevel}. " .
                "New retake courses: " . count($newRetakeCourseIds) . ". Total retakes tracked: " . count($updatedRetakes) . ". Fees auto-assigned: {$feesAssigned}.",
                $student,
                ['level' => $oldLevel, 'retake_courses' => $existingRetakes],
                ['level' => $nextLevel, 'retake_courses' => $updatedRetakes]
            );

            DB::commit();

            $message = "Student promoted from {$oldLevel} to {$nextLevel}.";
            if (count($newRetakeCourseIds) > 0) {
                $message .= " " . count($newRetakeCourseIds) . " failed course(s) tracked as retakes.";
            } else {
                $message .= " All current-level courses passed — no retakes.";
            }

            return $this->success($student->load(['user', 'department']), $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Promotion failed: ' . $e->getMessage(), 500);
        }
    }

    public function autoEnrollAll()
    {
        // First, fix any existing classes without a teacher assigned
        $this->assignTeachersToOrphanedClasses();

        $students = Student::where('status', 'active')->get();
        $totalEnrolled = 0;

        foreach ($students as $student) {
            $totalEnrolled += $this->autoEnrollStudent($student);
        }

        ActivityLog::log('auto_enroll', "Auto-enrolled all students. Total new enrollments: {$totalEnrolled}");

        return $this->success([
            'students_processed' => $students->count(),
            'total_enrollments' => $totalEnrolled,
        ], 'Auto-enrollment completed');
    }

    /**
     * Assign teachers to classes that have no teacher_id.
     * Picks a teacher from the same department with the fewest classes.
     */
    private function assignTeachersToOrphanedClasses(): void
    {
        $orphanedClasses = ClassModel::whereNull('teacher_id')
            ->with('course')
            ->get();

        foreach ($orphanedClasses as $class) {
            $departmentId = $class->course->department_id ?? null;
            if (!$departmentId) continue;

            $teacher = Teacher::where('department_id', $departmentId)
                ->where('status', 'active')
                ->withCount('classes')
                ->orderBy('classes_count', 'asc')
                ->first();

            if ($teacher) {
                $class->update(['teacher_id' => $teacher->id]);
            }
        }
    }

    public function courses(Student $student)
    {
        $enrollments = $student->enrollments()
            ->with(['class.course', 'class.teacher.user', 'class.schedules', 'latestGrade'])
            ->where('status', 'enrolled')
            ->get();

        return $this->success($enrollments);
    }

    public function grades(Student $student)
    {
        $enrollments = $student->enrollments()
            ->with(['class.course', 'grades' => function ($q) {
                $q->whereNotNull('validated_at');
            }])
            ->get();

        return $this->success($enrollments);
    }

    public function attendance(Student $student)
    {
        $enrollments = $student->enrollments()
            ->with(['class.course', 'attendance'])
            ->get();

        return $this->success($enrollments);
    }

    public function fees(Student $student)
    {
        $month = (int) date('n');
        $currentAcademicYear = $month >= 9
            ? date('Y') . '-' . (date('Y') + 1)
            : (date('Y') - 1) . '-' . date('Y');

        // Current academic year fees only (for the summary and main display)
        $currentFees = $student->fees()
            ->with(['feeType', 'payments'])
            ->where('academic_year', $currentAcademicYear)
            ->get();

        // All historical payments across every year (for the payment history section)
        $allPayments = \App\Models\Payment::whereHas('studentFee', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->with(['studentFee.feeType'])->orderByDesc('payment_date')->get();

        return $this->success([
            'fees'         => $currentFees,
            'academic_year' => $currentAcademicYear,
            'summary'      => [
                'total'   => $currentFees->sum('amount'),
                'paid'    => $currentFees->sum('paid_amount'),
                'balance' => $currentFees->sum(fn ($f) => (float) $f->amount - (float) $f->paid_amount),
            ],
            'all_payments' => $allPayments,
        ]);
    }

    // ==================== UNIFIED STUDENT MANAGEMENT (ADDITIVE) ====================

    /**
     * Get complete student profile with all details for admin view
     */
    public function getFullProfile(Student $student)
    {
        $student->load([
            'user',
            'department.faculty',
            'enrollments.class.course.department',
            'enrollments.class.teacher.user',
            'enrollments.class.schedules',
            'fees.feeType',
            'fees.payments',
        ]);

        // Get grades separately for better organization
        $grades = \App\Models\Grade::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->with(['enrollment.class.course'])->get();

        // Calculate statistics
        $totalCredits = $student->enrollments->sum(function ($enrollment) {
            return $enrollment->class->course->credits ?? 0;
        });

        $gradeAverage = $grades->count() > 0 ? $grades->avg('final_grade') : null;

        return $this->success([
            'student' => $student,
            'grades' => $grades,
            'statistics' => [
                'total_courses' => $student->enrollments->count(),
                'total_credits' => $totalCredits,
                'grade_average' => $gradeAverage ? round($gradeAverage, 2) : null,
                'attendance_rate' => $this->calculateAttendanceRate($student),
            ],
        ]);
    }

    /**
     * Calculate attendance rate for a student
     */
    private function calculateAttendanceRate(Student $student): ?float
    {
        $attendance = \App\Models\Attendance::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->get();

        if ($attendance->count() === 0) {
            return null;
        }

        $present = $attendance->where('status', 'present')->count();
        return round(($present / $attendance->count()) * 100, 1);
    }

    /**
     * Get all available courses for a student (for adding new courses)
     */
    public function getAvailableCourses(Student $student)
    {
        // Get courses the student is already enrolled in
        $enrolledCourseIds = $student->enrollments()
            ->with('class')
            ->get()
            ->pluck('class.course_id')
            ->unique();

        $retakeCourseIds = $student->retake_courses ?? [];

        // Get active courses from the student's own department (any type)
        // OR tronc_commun courses from any department (available to all students)
        $availableCourses = Course::with(['department.faculty'])
            ->where('is_active', true)
            ->whereNotIn('id', $enrolledCourseIds)
            ->where(function ($q) use ($student) {
                $q->where('department_id', $student->department_id)
                  ->orWhere('course_type', 'tronc_commun');
            })
            ->orderBy('department_id')
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(function ($course) use ($retakeCourseIds) {
                $course->is_retake = in_array($course->id, $retakeCourseIds);
                return $course;
            })
            ->sortByDesc('is_retake')
            ->values();

        return $this->success([
            'available_courses' => $availableCourses,
            'enrolled_count' => $enrolledCourseIds->count(),
            'retake_count' => count($retakeCourseIds),
        ]);
    }

    /**
     * Manually assign a course to a student (for transfer students)
     */
    public function assignCourse(Request $request, Student $student)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $course = Course::findOrFail($request->course_id);

        // Check if already enrolled in this course
        $existingEnrollment = $student->enrollments()
            ->whereHas('class', function ($q) use ($course) {
                $q->where('course_id', $course->id);
            })->first();

        if ($existingEnrollment) {
            return $this->error('Student is already enrolled in this course', 422);
        }

        DB::beginTransaction();
        try {
            // Find or create a class for this course
            $month = (int) date('n');
            $academicYear = $month >= 9 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');
            $courseSemester = $course->semester ?? ($month >= 9 ? '1' : '2');

            // Prefer an existing class that already has a teacher assigned (keeps student+teacher in sync)
            $class = ClassModel::where('course_id', $course->id)
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->orderByRaw('CASE WHEN teacher_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();

            if (!$class) {
                $class = ClassModel::create([
                    'course_id' => $course->id,
                    'academic_year' => $academicYear,
                    'semester' => $courseSemester,
                    'section' => 'A',
                    'room' => 'TBD',
                    'capacity' => 50,
                    'is_active' => true,
                ]);
            }

            // Create enrollment
            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'enrollment_date' => now(),
                'status' => 'enrolled',
            ]);

            DB::commit();

            ActivityLog::log(
                'course_assigned',
                "Assigned course '{$course->name}' to student {$student->user->full_name}" . 
                ($request->notes ? " - Notes: {$request->notes}" : ''),
                $student
            );

            return $this->success(
                $enrollment->load(['class.course', 'class.teacher.user']),
                "Course '{$course->name}' assigned successfully"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign course: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove a course from a student (for transfer students with already completed courses)
     */
    public function removeCourse(Request $request, Student $student, $enrollmentId)
    {
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', $student->id)
            ->with(['class.course'])
            ->first();

        if (!$enrollment) {
            return $this->error('Enrollment not found', 404);
        }

        $courseName = $enrollment->class->course->name;
        $reason = $request->input('reason', 'Course removed by administrator');

        // Soft delete or mark as dropped
        $enrollment->update([
            'status' => 'dropped',
            'drop_date' => now(),
        ]);

        ActivityLog::log(
            'course_removed',
            "Removed course '{$courseName}' from student {$student->user->full_name} - Reason: {$reason}",
            $student
        );

        return $this->success(null, "Course '{$courseName}' removed successfully");
    }

    /**
     * Get student's enrollment history (including dropped courses)
     */
    public function getEnrollmentHistory(Student $student)
    {
        $enrollments = $student->enrollments()
            ->with(['class.course.department', 'class.teacher.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [
            'active' => $enrollments->where('status', 'enrolled'),
            'completed' => $enrollments->where('status', 'completed'),
            'dropped' => $enrollments->where('status', 'dropped'),
        ];

        return $this->success($grouped);
    }

    /**
     * Bulk assign multiple courses to a student
     */
    public function bulkAssignCourses(Request $request, Student $student)
    {
        $request->validate([
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'exists:courses,id',
        ]);

        $month = (int) date('n');
        $academicYear = $month >= 9 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');
        $assignedCourses = [];
        $skippedCourses = [];

        DB::beginTransaction();
        try {
            foreach ($request->course_ids as $courseId) {
                $course = Course::find($courseId);

                // Check if already enrolled
                $existingEnrollment = $student->enrollments()
                    ->whereHas('class', function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    })->first();

                if ($existingEnrollment) {
                    $skippedCourses[] = $course->name;
                    continue;
                }

                $courseSemester = $course->semester ?? ($month >= 9 ? '1' : '2');

                // Prefer existing class with teacher assigned (keeps student+teacher in sync)
                $class = ClassModel::where('course_id', $courseId)
                    ->where('academic_year', $academicYear)
                    ->where('is_active', true)
                    ->orderByRaw('CASE WHEN teacher_id IS NOT NULL THEN 0 ELSE 1 END')
                    ->first();

                if (!$class) {
                    $class = ClassModel::create([
                        'course_id' => $courseId,
                        'academic_year' => $academicYear,
                        'semester' => $courseSemester,
                        'section' => 'A',
                        'room' => 'TBD',
                        'capacity' => 50,
                        'is_active' => true,
                    ]);
                }

                // Create enrollment
                Enrollment::create([
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'enrollment_date' => now(),
                    'status' => 'enrolled',
                ]);

                $assignedCourses[] = $course->name;
            }

            DB::commit();

            ActivityLog::log(
                'bulk_course_assigned',
                "Bulk assigned " . count($assignedCourses) . " courses to student {$student->user->full_name}",
                $student
            );

            return $this->success([
                'assigned' => $assignedCourses,
                'skipped' => $skippedCourses,
            ], count($assignedCourses) . ' courses assigned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign courses: ' . $e->getMessage(), 500);
        }
    }
}
