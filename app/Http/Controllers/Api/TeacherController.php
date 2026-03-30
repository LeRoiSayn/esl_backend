<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = Teacher::with(['user', 'department.faculty']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
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
                })->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($teachers);
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
            'qualification' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'hire_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
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
                'role' => 'teacher',
            ]);

            // Generate employee ID
            $lastTeacher = Teacher::orderBy('id', 'desc')->first();
            $employeeNumber = $lastTeacher ? intval(substr($lastTeacher->employee_id, 4)) + 1 : 1;
            $employeeId = 'EMP-' . str_pad($employeeNumber, 5, '0', STR_PAD_LEFT);

            // Create teacher
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'department_id' => $request->department_id,
                'employee_id' => $employeeId,
                'qualification' => $request->qualification,
                'specialization' => $request->specialization,
                'hire_date' => $request->hire_date,
                'salary' => $request->salary,
            ]);

            DB::commit();

            ActivityLog::log('create', "Created teacher: {$user->full_name}", $teacher);

            return $this->success(
                $teacher->load(['user', 'department.faculty']),
                'Teacher created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create teacher: ' . $e->getMessage(), 500);
        }
    }

    public function show(Teacher $teacher)
    {
        $teacher->load([
            'user',
            'department.faculty',
            'classes.course',
            'classes.enrollments',
        ]);

        return $this->success($teacher);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'department_id' => 'sometimes|exists:departments,id',
            'qualification' => 'sometimes|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,on_leave',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Update user
            $userFields = $request->only(['first_name', 'last_name', 'phone', 'address', 'date_of_birth', 'gender']);
            if (!empty($request->password)) {
                $userFields['password'] = Hash::make($request->password);
            }
            $teacher->user->update($userFields);

            // Update teacher
            $teacher->update($request->only([
                'department_id', 'qualification', 'specialization', 'salary', 'status'
            ]));

            DB::commit();

            ActivityLog::log('update', "Updated teacher: {$teacher->user->full_name}", $teacher);

            return $this->success($teacher->load(['user', 'department.faculty']), 'Teacher updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update teacher: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Teacher $teacher)
    {
        if ($teacher->classes()->where('is_active', true)->count() > 0) {
            return $this->error('Cannot delete teacher with active classes', 400);
        }

        $name = $teacher->user->full_name;
        
        $teacher->user->delete(); // This will cascade delete the teacher

        ActivityLog::log('delete', "Deleted teacher: {$name}");

        return $this->success(null, 'Teacher deleted successfully');
    }

    public function classes(Teacher $teacher)
    {
        $classes = $teacher->classes()
            ->with(['course', 'enrollments.student.user', 'schedules'])
            ->where('is_active', true)
            ->get();

        return $this->success($classes);
    }

    public function students(Teacher $teacher)
    {
        $classIds = $teacher->classes()->pluck('id');
        
        $students = \App\Models\Enrollment::with(['student.user', 'class.course'])
            ->whereIn('class_id', $classIds)
            ->where('status', 'enrolled')
            ->get()
            ->groupBy('class_id');

        return $this->success($students);
    }

    // ==================== UNIFIED TEACHER MANAGEMENT (ADDITIVE) ====================

    /**
     * Get complete teacher profile with all details for admin view
     */
    public function getFullProfile(Teacher $teacher)
    {
        $teacher->load([
            'user',
            'department.faculty',
            'classes.course.department',
            'classes.enrollments.student.user',
            'classes.schedules',
        ]);

        // Calculate statistics
        $activeClasses = $teacher->classes->where('is_active', true);
        $totalStudents = $activeClasses->sum(function ($class) {
            return $class->enrollments->where('status', 'enrolled')->count();
        });

        return $this->success([
            'teacher' => $teacher,
            'statistics' => [
                'total_classes' => $activeClasses->count(),
                'total_students' => $totalStudents,
                'total_courses' => $activeClasses->pluck('course_id')->unique()->count(),
            ],
        ]);
    }

    /**
     * Get all courses available for assignment to a teacher
     */
    public function getAvailableCourses(Teacher $teacher)
    {
        // Get courses already assigned to this teacher (active classes)
        $assignedCourseIds = $teacher->classes()
            ->where('is_active', true)
            ->pluck('course_id')
            ->unique();

        // Get all active courses, optionally filter by teacher's department
        $availableCourses = \App\Models\Course::with(['department.faculty'])
            ->where('is_active', true)
            ->orderBy('department_id')
            ->orderBy('name')
            ->get()
            ->map(function ($course) use ($assignedCourseIds) {
                $course->is_assigned = $assignedCourseIds->contains($course->id);
                return $course;
            });

        return $this->success([
            'courses' => $availableCourses,
            'assigned_count' => $assignedCourseIds->count(),
        ]);
    }

    /**
     * Get teacher's assigned courses (classes)
     */
    public function getAssignedCourses(Teacher $teacher)
    {
        $classes = $teacher->classes()
            ->with(['course.department', 'enrollments.student.user', 'schedules'])
            ->orderBy('is_active', 'desc')
            ->orderBy('academic_year', 'desc')
            ->get();

        return $this->success($classes);
    }

    /**
     * Assign a course to a teacher (Admin only)
     */
    public function assignCourse(Request $request, Teacher $teacher)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|in:1,2,3',
            'section' => 'nullable|string|max:10',
            'room' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:500',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $month = (int) date('n');
        $academicYear = $request->academic_year ?? ($month >= 9 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y'));
        $semester = $request->semester ?? ($course->semester ?? ($month >= 9 ? '1' : '2'));
        $section = $request->section ?? 'A';

        // Check if this course is already assigned to this teacher for this academic year
        $teacherClass = ClassModel::where('course_id', $course->id)
            ->where('teacher_id', $teacher->id)
            ->where('academic_year', $academicYear)
            ->where('is_active', true)
            ->first();

        if ($teacherClass) {
            // Teacher already has this course. Check for orphaned classes (students enrolled elsewhere).
            $orphanedClasses = ClassModel::where('course_id', $course->id)
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->where('id', '!=', $teacherClass->id)
                ->get();

            if ($orphanedClasses->isNotEmpty()) {
                DB::beginTransaction();
                try {
                    foreach ($orphanedClasses as $orphan) {
                        // Move enrollments to teacher's class
                        Enrollment::where('class_id', $orphan->id)
                            ->update(['class_id' => $teacherClass->id]);
                        // Move schedules if teacher's class has none yet
                        if ($teacherClass->schedules()->count() === 0) {
                            Schedule::where('class_id', $orphan->id)
                                ->update(['class_id' => $teacherClass->id]);
                        }
                        $orphan->update(['is_active' => false]);
                    }
                    DB::commit();
                    ActivityLog::log('class_merge', "Merged {$orphanedClasses->count()} orphaned class(es) into teacher's class for course {$course->name}", $teacher);
                    return $this->success(
                        $teacherClass->fresh()->load(['course', 'teacher.user']),
                        "Enrollments synchronized: {$orphanedClasses->count()} orphaned class(es) merged successfully."
                    );
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->error('Failed to merge classes: ' . $e->getMessage(), 500);
                }
            }

            return $this->error('This course is already assigned to this teacher for the selected period', 422);
        }

        DB::beginTransaction();
        try {
            // Find any existing class for this course in this academic year (regardless of semester/section)
            // This ensures teacher assignment links to the same class students are enrolled in
            $classWithOtherTeacher = ClassModel::where('course_id', $course->id)
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->where(function ($q) use ($teacher) {
                    $q->whereNull('teacher_id')
                      ->orWhere('teacher_id', '!=', $teacher->id);
                })
                ->first();

            if ($classWithOtherTeacher) {
                // Update the existing class to assign this teacher
                $classWithOtherTeacher->update(['teacher_id' => $teacher->id]);
                $class = $classWithOtherTeacher;
            } else {
                // No existing class — create a new one
                $class = ClassModel::create([
                    'course_id' => $course->id,
                    'teacher_id' => $teacher->id,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'section' => $section,
                    'room' => $request->room ?? 'TBD',
                    'capacity' => $request->capacity ?? 50,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            \App\Models\ActivityLog::log(
                'course_assigned_to_teacher',
                "Assigned course '{$course->name}' to teacher {$teacher->user->full_name}",
                $teacher
            );

            return $this->success(
                $class->load(['course', 'teacher.user']),
                "Course '{$course->name}' assigned to {$teacher->user->full_name} successfully"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign course: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove a course from a teacher (Admin only)
     */
    public function removeCourse(Request $request, Teacher $teacher, $classId)
    {
        $class = \App\Models\ClassModel::where('id', $classId)
            ->where('teacher_id', $teacher->id)
            ->with('course')
            ->first();

        if (!$class) {
            return $this->error('Class not found or not assigned to this teacher', 404);
        }

        // Check if there are enrolled students
        $enrolledCount = $class->enrollments()->where('status', 'enrolled')->count();
        
        if ($enrolledCount > 0 && !$request->input('force', false)) {
            return $this->error(
                "Cannot remove course with {$enrolledCount} enrolled students. Use force=true to override.",
                422
            );
        }

        $courseName = $class->course->name;

        // Option 1: Just unassign the teacher (keep the class)
        if ($request->input('unassign_only', false)) {
            $class->update(['teacher_id' => null]);
            $message = "Teacher unassigned from course '{$courseName}'";
        } else {
            // Option 2: Deactivate the class
            $class->update(['is_active' => false, 'teacher_id' => null]);
            $message = "Course '{$courseName}' removed from teacher and deactivated";
        }

        \App\Models\ActivityLog::log(
            'course_removed_from_teacher',
            "Removed course '{$courseName}' from teacher {$teacher->user->full_name}",
            $teacher
        );

        return $this->success(null, $message);
    }

    /**
     * Bulk assign multiple courses to a teacher
     */
    public function bulkAssignCourses(Request $request, Teacher $teacher)
    {
        $request->validate([
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'exists:courses,id',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|in:1,2,3',
        ]);

        $academicYear = $request->academic_year ?? (date('Y') . '-' . (date('Y') + 1));
        $semester = $request->semester ?? (date('n') <= 6 ? '2' : '1');
        $assignedCourses = [];
        $skippedCourses = [];

        DB::beginTransaction();
        try {
            foreach ($request->course_ids as $courseId) {
                $course = \App\Models\Course::find($courseId);

                // Check if already assigned
                $existingClass = \App\Models\ClassModel::where('course_id', $courseId)
                    ->where('teacher_id', $teacher->id)
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester)
                    ->first();

                if ($existingClass) {
                    $skippedCourses[] = $course->name;
                    continue;
                }

                // Create or update class
                $class = \App\Models\ClassModel::updateOrCreate(
                    [
                        'course_id' => $courseId,
                        'academic_year' => $academicYear,
                        'semester' => $semester,
                        'section' => 'A',
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'room' => 'TBD',
                        'capacity' => 50,
                        'is_active' => true,
                    ]
                );

                $assignedCourses[] = $course->name;
            }

            DB::commit();

            \App\Models\ActivityLog::log(
                'bulk_course_assigned_to_teacher',
                "Bulk assigned " . count($assignedCourses) . " courses to teacher {$teacher->user->full_name}",
                $teacher
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

    /**
     * Get workload summary for a teacher
     */
    public function getWorkload(Teacher $teacher)
    {
        $classes = $teacher->classes()
            ->where('is_active', true)
            ->with(['course', 'enrollments', 'schedules'])
            ->get();

        $totalHoursPerWeek = $classes->sum(function ($class) {
            return $class->schedules->count() * 1.5; // Assume 1.5 hours per session
        });

        $totalStudents = $classes->sum(function ($class) {
            return $class->enrollments->where('status', 'enrolled')->count();
        });

        return $this->success([
            'classes' => $classes,
            'summary' => [
                'total_classes' => $classes->count(),
                'total_students' => $totalStudents,
                'hours_per_week' => $totalHoursPerWeek,
                'courses' => $classes->pluck('course.name')->unique()->values(),
            ],
        ]);
    }
}
