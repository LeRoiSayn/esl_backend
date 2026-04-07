<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Enrollment;
use App\Models\ActivityLog;
use App\Models\ClassModel;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function index(Request $request)
    {
        $query = Grade::with(['enrollment.student.user', 'enrollment.class.course', 'gradedBy']);

        if ($request->has('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        if ($request->has('class_id')) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        $grades = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($grades);
    }

    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id'         => 'required|exists:enrollments,id',
            'attendance_score'      => 'nullable|numeric|min:0|max:10',
            'quiz_score'            => 'nullable|numeric|min:0|max:20',
            'continuous_assessment' => 'nullable|numeric|min:0|max:30',
            'exam_score'            => 'nullable|numeric|min:0|max:40',
            'remarks'               => 'nullable|string',
        ]);

        $attendance = (float)($request->attendance_score ?? 0);
        $quiz       = (float)($request->quiz_score ?? 0);
        $ca         = (float)($request->continuous_assessment ?? 0);
        $exam       = (float)($request->exam_score ?? 0);
        $finalGrade = Grade::calculateFinalGrade($ca, $exam, $quiz, $attendance);
        $letterGrade = Grade::calculateLetterGrade($finalGrade);

        $grade = Grade::create([
            'enrollment_id'         => $request->enrollment_id,
            'attendance_score'      => $request->attendance_score,
            'quiz_score'            => $request->quiz_score,
            'continuous_assessment' => $request->continuous_assessment,
            'exam_score'            => $request->exam_score,
            'final_grade'           => $finalGrade,
            'letter_grade'          => $letterGrade,
            'remarks'               => $request->remarks,
            'graded_by'             => auth()->id(),
            'graded_at'             => now(),
        ]);

        ActivityLog::log('create', 'Grade recorded', $grade);

        return $this->success(
            $grade->load(['enrollment.student.user', 'enrollment.class.course']),
            'Grade recorded successfully',
            201
        );
    }

    public function show(Grade $grade)
    {
        $grade->load(['enrollment.student.user', 'enrollment.class.course', 'gradedBy']);
        
        return $this->success($grade);
    }

    public function update(Request $request, Grade $grade)
    {
        $request->validate([
            'attendance_score'      => 'nullable|numeric|min:0|max:10',
            'quiz_score'            => 'nullable|numeric|min:0|max:20',
            'continuous_assessment' => 'nullable|numeric|min:0|max:30',
            'exam_score'            => 'nullable|numeric|min:0|max:40',
            'remarks'               => 'nullable|string',
        ]);

        $attendance = (float)($request->attendance_score  ?? $grade->attendance_score  ?? 0);
        $quiz       = (float)($request->quiz_score        ?? $grade->quiz_score        ?? 0);
        $ca         = (float)($request->continuous_assessment ?? $grade->continuous_assessment ?? 0);
        $exam       = (float)($request->exam_score        ?? $grade->exam_score        ?? 0);
        $finalGrade  = Grade::calculateFinalGrade($ca, $exam, $quiz, $attendance);
        $letterGrade = Grade::calculateLetterGrade($finalGrade);

        $grade->update([
            'attendance_score'      => $request->attendance_score      ?? $grade->attendance_score,
            'quiz_score'            => $request->quiz_score            ?? $grade->quiz_score,
            'continuous_assessment' => $request->continuous_assessment ?? $grade->continuous_assessment,
            'exam_score'            => $request->exam_score            ?? $grade->exam_score,
            'final_grade'           => $finalGrade,
            'letter_grade'          => $letterGrade,
            'remarks'               => $request->remarks               ?? $grade->remarks,
            'graded_by'             => auth()->id(),
            'graded_at'             => now(),
        ]);

        ActivityLog::log('update', 'Grade updated', $grade);

        return $this->success($grade->load(['enrollment.student.user', 'enrollment.class.course']), 'Grade updated successfully');
    }

    public function destroy(Grade $grade)
    {
        ActivityLog::log('delete', 'Grade deleted', $grade);
        
        $grade->delete();

        return $this->success(null, 'Grade deleted successfully');
    }

    public function byClass(int $classId)
    {
        $enrollments = Enrollment::with(['student.user', 'grades'])
            ->where('class_id', $classId)
            ->where('status', 'enrolled')
            ->get();

        return $this->success($enrollments);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'grades'                        => 'required|array',
            'grades.*.enrollment_id'        => 'required|exists:enrollments,id',
            'grades.*.attendance_score'     => 'nullable|numeric|min:0|max:10',
            'grades.*.quiz_score'           => 'nullable|numeric|min:0|max:20',
            'grades.*.continuous_assessment'=> 'nullable|numeric|min:0|max:30',
            'grades.*.exam_score'           => 'nullable|numeric|min:0|max:40',
        ]);

        $updated = 0;
        foreach ($request->grades as $gradeData) {
            $attendance = (float)($gradeData['attendance_score']  ?? 0);
            $quiz       = (float)($gradeData['quiz_score']        ?? 0);
            $ca         = (float)($gradeData['continuous_assessment'] ?? 0);
            $exam       = (float)($gradeData['exam_score']        ?? 0);
            $finalGrade  = Grade::calculateFinalGrade($ca, $exam, $quiz, $attendance);
            $letterGrade = Grade::calculateLetterGrade($finalGrade);

            Grade::updateOrCreate(
                ['enrollment_id' => $gradeData['enrollment_id']],
                [
                    'attendance_score'      => $gradeData['attendance_score']      ?? null,
                    'quiz_score'            => $gradeData['quiz_score']            ?? null,
                    'continuous_assessment' => $gradeData['continuous_assessment'] ?? null,
                    'exam_score'            => $gradeData['exam_score']            ?? null,
                    'final_grade'           => $finalGrade,
                    'letter_grade'          => $letterGrade,
                    'graded_by'             => auth()->id(),
                    'graded_at'             => now(),
                ]
            );
            $updated++;
        }

        ActivityLog::log('bulk_update', "Bulk updated {$updated} grades");

        return $this->success(['updated' => $updated], "Updated {$updated} grades successfully");
    }

    /**
     * Teacher submits final grades for a class to admin review.
     * Creates a notification for all admin users.
     */
    public function submitToAdmin(Request $request, int $classId)
    {
        $class = \App\Models\ClassModel::with(['course', 'teacher.user'])->findOrFail($classId);
        $teacher = $request->user()->teacher;

        // Only the teacher who owns this class may submit
        if (!$teacher || $class->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $gradedCount = Grade::whereHas('enrollment', fn($q) => $q->where('class_id', $classId))->count();
        $totalCount  = \App\Models\Enrollment::where('class_id', $classId)->where('status', 'enrolled')->count();

        $teacherName = trim(($teacher->user->first_name ?? '') . ' ' . ($teacher->user->last_name ?? ''));
        $courseName  = $class->course->name ?? 'Cours ' . $classId;

        // Notify all admin users
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'grades_submitted',
                'title'   => 'Notes soumises pour validation',
                'message' => "L'enseignant {$teacherName} a soumis les notes de {$courseName} ({$gradedCount}/{$totalCount} étudiants notés).",
                'link'    => '/admin/students',
                'data'    => [
                    'class_id'    => $classId,
                    'course_name' => $courseName,
                    'teacher'     => $teacherName,
                    'graded'      => $gradedCount,
                    'total'       => $totalCount,
                ],
            ]);
        }

        ActivityLog::log('submit_grades', "Teacher submitted grades for class {$classId} ({$courseName})");

        return $this->success([
            'graded' => $gradedCount,
            'total'  => $totalCount,
        ], "Notes soumises à l'administration avec succès.");
    }

    /**
     * Admin overview: all active classes with grade counts and submission status.
     */
    public function adminOverview()
    {
        $classes = ClassModel::with(['course', 'teacher.user'])
            ->where('is_active', true)
            ->get()
            ->map(function ($class) {
                $totalEnrolled = Enrollment::where('class_id', $class->id)
                    ->where('status', 'enrolled')
                    ->count();
                $graded = Grade::whereHas(
                    'enrollment',
                    fn($q) => $q->where('class_id', $class->id)
                )->count();

                // Check submission status: submitted, validated, or rejected
                $latestSubmission = Notification::where('type', 'grades_submitted')
                    ->whereJsonContains('data->class_id', $class->id)
                    ->latest()
                    ->first();

                $latestValidation = Notification::whereIn('type', ['grades_validated', 'grades_rejected'])
                    ->whereJsonContains('data->class_id', $class->id)
                    ->latest()
                    ->first();

                $submitted = $latestSubmission !== null;

                // Determine status, with fallback: if ALL enrolled students are graded
                // but no submission notification exists (e.g. seeded data), treat as submitted.
                $allGraded = $totalEnrolled > 0 && $graded >= $totalEnrolled;

                $status = 'pending'; // not yet submitted
                if ($submitted || $allGraded) {
                    $status = 'submitted'; // submitted (explicit or implicit), awaiting review
                }
                if ($latestValidation) {
                    if (!$latestSubmission || $latestValidation->created_at->gte($latestSubmission->created_at)) {
                        $status = $latestValidation->type === 'grades_validated' ? 'validated' : 'rejected';
                    }
                }

                return [
                    'id'            => $class->id,
                    'name'          => $class->name,
                    'course'        => $class->course?->name ?? '—',
                    'code'          => $class->course?->code ?? '—',
                    'teacher'       => trim(
                        ($class->teacher?->user?->first_name ?? '') . ' ' .
                        ($class->teacher?->user?->last_name  ?? '')
                    ),
                    'teacher_id'    => $class->teacher_id,
                    'total_enrolled'=> $totalEnrolled,
                    'graded'        => $graded,
                    'submitted'     => $submitted || $allGraded,
                    'status'        => $status,
                    'rejection_reason' => ($status === 'rejected' && $latestValidation)
                        ? ($latestValidation->data['reason'] ?? null)
                        : null,
                ];
            });

        return $this->success($classes->values()->all());
    }

    /**
     * Admin validates grades for a class.
     * Stamps validated_at, notifies teacher + all enrolled students.
     */
    public function validateClass(Request $request, int $classId)
    {
        $class = ClassModel::with(['course', 'teacher.user'])->findOrFail($classId);
        $courseName = $class->course->name ?? 'Cours ' . $classId;

        // Stamp validated_at on all grades for this class
        $adminId = $request->user()->id;
        Grade::whereHas('enrollment', fn($q) => $q->where('class_id', $classId))
            ->whereNull('validated_at')
            ->update(['validated_at' => now(), 'validated_by' => $adminId]);

        // Notify the teacher
        if ($class->teacher && $class->teacher->user) {
            Notification::create([
                'user_id' => $class->teacher->user->id,
                'type'    => 'grades_validated',
                'title'   => 'Notes validées',
                'message' => "Les notes de {$courseName} ont été validées par l'administration.",
                'link'    => '/teacher/grades',
                'data'    => [
                    'class_id'    => $classId,
                    'course_name' => $courseName,
                    'validated_by'=> $adminId,
                ],
            ]);
        }

        // Notify all enrolled students
        $enrollments = Enrollment::with('student.user')
            ->where('class_id', $classId)
            ->where('status', 'enrolled')
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->student && $enrollment->student->user) {
                Notification::create([
                    'user_id' => $enrollment->student->user->id,
                    'type'    => 'grades_validated',
                    'title'   => 'Vos notes sont disponibles',
                    'message' => "Les notes de {$courseName} ont été validées. Consultez votre relevé de notes.",
                    'link'    => '/student/grades',
                    'data'    => [
                        'class_id'    => $classId,
                        'course_name' => $courseName,
                    ],
                ]);
            }
        }

        // Admin-side record for tracking
        Notification::create([
            'user_id' => $adminId,
            'type'    => 'grades_validated',
            'title'   => 'Notes validées',
            'message' => "Vous avez validé les notes de {$courseName}.",
            'link'    => '/admin/grades',
            'data'    => [
                'class_id'    => $classId,
                'course_name' => $courseName,
            ],
        ]);

        ActivityLog::log('validate_grades', "Admin validated grades for class {$classId} ({$courseName})");

        return $this->success(null, "Les notes de {$courseName} ont été validées avec succès.");
    }

    /**
     * Admin rejects grades for a class with a reason.
     * Notifies the teacher who submitted.
     */
    public function rejectClass(Request $request, int $classId)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $class = ClassModel::with(['course', 'teacher.user'])->findOrFail($classId);
        $courseName = $class->course->name ?? 'Cours ' . $classId;
        $reason = $request->reason;

        // Notify the teacher
        if ($class->teacher && $class->teacher->user) {
            Notification::create([
                'user_id' => $class->teacher->user->id,
                'type'    => 'grades_rejected',
                'title'   => 'Notes refusées',
                'message' => "Les notes de {$courseName} ont été refusées. Motif : {$reason}",
                'link'    => '/teacher/grades',
                'data'    => [
                    'class_id'    => $classId,
                    'course_name' => $courseName,
                    'reason'      => $reason,
                    'rejected_by' => $request->user()->id,
                ],
            ]);
        }

        // Admin-side record
        Notification::create([
            'user_id' => $request->user()->id,
            'type'    => 'grades_rejected',
            'title'   => 'Notes refusées',
            'message' => "Vous avez refusé les notes de {$courseName}. Motif : {$reason}",
            'link'    => '/admin/grades',
            'data'    => [
                'class_id'    => $classId,
                'course_name' => $courseName,
                'reason'      => $reason,
            ],
        ]);

        ActivityLog::log('reject_grades', "Admin rejected grades for class {$classId} ({$courseName}). Reason: {$reason}");

        return $this->success(null, "Les notes de {$courseName} ont été refusées.");
    }
}
