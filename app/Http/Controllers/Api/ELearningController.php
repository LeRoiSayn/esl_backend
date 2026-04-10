<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineCourse;
use App\Models\CourseMaterial;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\OnlineCourseAttendance;
use App\Models\Enrollment;
use App\Models\ClassModel;
use App\Models\Notification;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ELearningController extends Controller
{
    /**
     * Get authenticated student or fail
     */
    private function getStudent(Request $request)
    {
        $user = $request->user();
        $student = $user->student ?? null;
        
        if (!$student) {
            abort(404, 'Student profile not found');
        }
        
        return $student;
    }
    
    /**
     * Get authenticated teacher or fail
     */
    private function getTeacher(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher ?? null;
        
        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }
        
        return $teacher;
    }

    /**
     * Get enrolled course IDs for a student (enrollments → classes → courses)
     */
    private function getEnrolledCourseIds($studentId)
    {
        return Enrollment::where('enrollments.student_id', $studentId)
            ->join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->pluck('classes.course_id')
            ->unique()
            ->values();
    }

    /**
     * Check if a student is enrolled in a specific course (through classes)
     */
    private function isStudentEnrolledInCourse($studentId, $courseId)
    {
        return Enrollment::where('enrollments.student_id', $studentId)
            ->join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('classes.course_id', $courseId)
            ->exists();
    }

    /**
     * Get class IDs a student is enrolled in for a specific course
     */
    private function getStudentClassIdsForCourse($studentId, $courseId)
    {
        return Enrollment::where('enrollments.student_id', $studentId)
            ->join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('classes.course_id', $courseId)
            ->pluck('enrollments.class_id')
            ->toArray();
    }

    // ==================== ONLINE COURSES ====================

    /**
     * Get online courses for a teacher
     */
    public function getTeacherCourses(Request $request)
    {
        $teacher = $this->getTeacher($request);
        
        $courses = OnlineCourse::with(['course'])
            ->withCount('attendance')
            ->where('teacher_id', $teacher->id)
            ->orderBy('scheduled_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['courses' => $courses]);
    }

    /**
     * Get online courses for a student (enrolled courses only)
     */
    public function getStudentCourses(Request $request)
    {
        $student = $this->getStudent($request);
        
        // Get enrolled course IDs through classes
        $enrolledCourseIds = $this->getEnrolledCourseIds($student->id);

        $courses = OnlineCourse::with(['course', 'teacher.user'])
            ->whereIn('course_id', $enrolledCourseIds)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json(['courses' => $courses]);
    }

    /**
     * Create a new online course session
     */
    public function createOnlineCourse(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'external_url' => 'required|url',
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
        ]);

        $teacher = $this->getTeacher($request);

        // Ensure teacher owns the class
        $class = \App\Models\ClassModel::findOrFail($request->class_id);
        if ($class->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé: vous n\'enseignez pas cette classe'], 403);
        }

        $courseId = $class->course_id;

        $onlineCourse = OnlineCourse::create([
            'course_id' => $courseId,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => 'live',
            'meeting_url' => $request->external_url,
            'meeting_id' => null,
            'scheduled_at' => $request->scheduled_at,
            'duration_minutes' => $request->duration_minutes ?? 60,
            'status' => 'scheduled',
        ]);

        $onlineCourse->load(['course']);
        $onlineCourse->loadCount('attendance');

        return response()->json([
            'message' => 'Cours en ligne créé avec succès',
            'course' => $onlineCourse,
        ], 201);
    }

    /**
     * Teacher: attendance report for an online session (participants list).
     */
    public function onlineCourseAttendanceReport(Request $request, $id)
    {
        $teacher = $this->getTeacher($request);
        $session = OnlineCourse::with(['course', 'attendance.student.user'])
            ->findOrFail($id);

        if ($session->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $attendees = $session->attendance->map(function ($row) {
            $student = $row->student;
            $user = $student?->user;

            return [
                'id' => $row->id,
                'joined_at' => $row->joined_at,
                'left_at' => $row->left_at,
                'duration_minutes' => $row->duration_minutes,
                'student' => [
                    'id' => $student->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'student_id' => $student->student_id,
                ],
            ];
        })->values();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'description' => $session->description,
                'scheduled_at' => $session->scheduled_at,
                'duration_minutes' => $session->duration_minutes,
                'status' => $session->status,
                'course_name' => $session->course->name ?? null,
            ],
            'attendees' => $attendees,
            'attendee_count' => $attendees->count(),
        ]);
    }

    /**
     * Join an online course (student)
     */
    public function joinOnlineCourse(Request $request, $id)
    {
        $student = $this->getStudent($request);
        $onlineCourse = OnlineCourse::findOrFail($id);

        // Check if student is enrolled in the course (through classes)
        if (!$this->isStudentEnrolledInCourse($student->id, $onlineCourse->course_id)) {
            return response()->json([
                'error' => 'Vous n\'êtes pas inscrit à ce cours',
            ], 403);
        }

        // Record attendance
        OnlineCourseAttendance::updateOrCreate(
            ['online_course_id' => $id, 'student_id' => $student->id],
            ['joined_at' => now()]
        );

        return response()->json([
            'meeting_url' => $onlineCourse->meeting_url,
            'meeting_id' => $onlineCourse->meeting_id,
        ]);
    }

    /**
     * Leave an online course (update duration)
     */
    public function leaveOnlineCourse(Request $request, $id)
    {
        $student = $this->getStudent($request);
        
        $attendance = OnlineCourseAttendance::where('online_course_id', $id)
            ->where('student_id', $student->id)
            ->first();

        if ($attendance) {
            $attendance->left_at = now();
            $attendance->duration_minutes = $attendance->joined_at->diffInMinutes(now());
            $attendance->save();
        }

        return response()->json(['message' => 'Déconnexion enregistrée']);
    }

    /**
     * Teacher starts (goes live) an online course session.
     */
    public function startOnlineCourse(Request $request, $id)
    {
        $teacher = $this->getTeacher($request);
        $course  = OnlineCourse::findOrFail($id);

        if ($course->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $course->update(['status' => 'live']);

        return response()->json([
            'message'     => 'Session démarrée',
            'meeting_url' => $course->meeting_url,
            'course'      => $course->fresh()->loadCount('attendance'),
        ]);
    }

    /**
     * Teacher ends (marks as completed) a live online course session.
     */
    public function endOnlineCourse(Request $request, $id)
    {
        $teacher = $this->getTeacher($request);
        $course  = OnlineCourse::findOrFail($id);

        if ($course->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        if ($course->status !== 'live') {
            return response()->json(['error' => 'La session n\'est pas en cours'], 422);
        }

        $course->update(['status' => 'ended']);

        return response()->json([
            'message' => 'Session terminée',
            'course'  => $course->fresh()->loadCount('attendance'),
        ]);
    }

    /**
     * Teacher updates an online course session.
     */
    public function updateOnlineCourse(Request $request, $id)
    {
        $teacher = $this->getTeacher($request);
        $course  = OnlineCourse::findOrFail($id);

        if ($course->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'meeting_url'      => 'nullable|url',
            'scheduled_at'     => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $course->update(array_filter([
            'title'            => $request->title,
            'description'      => $request->description,
            'meeting_url'      => $request->meeting_url,
            'scheduled_at'     => $request->scheduled_at,
            'duration_minutes' => $request->duration_minutes,
        ], fn($v) => !is_null($v)));

        return response()->json(['course' => $course->fresh()->loadCount('attendance')]);
    }

    // ==================== COURSE MATERIALS ====================

    /**
     * Upload course material
     */
    public function uploadMaterial(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'class_id' => 'nullable|exists:classes,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:pdf,video,document,presentation,image,other,link',
            'file' => 'nullable|file|max:51200', // 50MB max
            'external_url' => 'nullable|url',
            'downloadable' => 'boolean',
        ]);

        $teacher = $this->getTeacher($request);

        // If class_id provided, ensure the teacher owns that class
        if ($request->class_id) {
            $class = \App\Models\ClassModel::findOrFail($request->class_id);
            if ($class->teacher_id !== $teacher->id) {
                return response()->json(['error' => 'Accès refusé: vous n\'enseignez pas cette classe'], 403);
            }
        }

        $path = null;
        $fileName = null;
        $fileSize = 0;

        if ($request->type === 'link') {
            if (!$request->external_url) {
                return response()->json(['error' => 'external_url requis pour les matériaux de type link'], 422);
            }
        } else {
            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'Fichier requis pour ce type de matériau'], 422);
            }
            $file = $request->file('file');
            $path = $file->store('materials/' . $request->course_id, 'public');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
        }

        $material = CourseMaterial::create([
            'course_id' => $request->course_id,
            'class_id' => $request->class_id ?? null,
            'teacher_id' => $teacher->id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'file_path' => $path,
            'external_url' => $request->external_url ?? null,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'downloadable' => $request->downloadable ?? true,
        ]);

        // Notify enrolled students about new material
        $course = Course::find($request->course_id);
        $teacherName = $teacher->user->first_name ?? 'Un professeur';
        $enrolledUserIds = Enrollment::join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->where('classes.course_id', $request->course_id)
            ->where('enrollments.status', 'enrolled')
            ->pluck('students.user_id');

        foreach ($enrolledUserIds as $userId) {
            Notification::notify(
                $userId,
                'new_material',
                'Nouveau document partagé',
                $teacherName . ' a partagé "' . $material->title . '" dans ' . ($course?->name ?? 'votre cours'),
                '/student/elearning',
                ['course_id' => $material->course_id, 'material_id' => $material->id]
            );
        }

        return response()->json([
            'message' => 'Document uploadé avec succès',
            'material' => $material,
        ], 201);
    }

    /**
     * Get course materials for students
     */
    public function getCourseMaterials(Request $request, $courseId)
    {
        $user = $request->user();
        $classIds = [];
        
        // Check access
        if ($user->role === 'student') {
            // Get classes the student is enrolled in for this course (through classes table)
            $classIds = $this->getStudentClassIdsForCourse($user->student->id, $courseId);

            if (empty($classIds)) {
                return response()->json(['error' => 'Accès refusé'], 403);
            }
        }

        $materialsQuery = CourseMaterial::where('course_id', $courseId);

        if ($user->role === 'student') {
            // students see materials which are global (class_id NULL) or for their class(es)
            $materialsQuery->where(function ($q) use ($classIds) {
                $q->whereNull('class_id')->orWhereIn('class_id', $classIds);
            });
        }

        $materials = $materialsQuery->orderBy('created_at', 'desc')->get();

        return response()->json(['materials' => $materials]);
    }

    /**
     * Download a material
     */
    public function downloadMaterial(Request $request, $id)
    {
        $material = CourseMaterial::findOrFail($id);
        $user = $request->user();

        // Check access
        if ($user->role === 'student') {
            if (!$this->isStudentEnrolledInCourse($user->student->id, $material->course_id)) {
                return response()->json(['error' => 'Accès refusé'], 403);
            }

            if (!$material->downloadable) {
                return response()->json(['error' => 'Téléchargement non autorisé'], 403);
            }
        }

        $material->incrementDownloads();

        return Storage::disk('public')->download($material->file_path, $material->file_name);
    }

    // ==================== QUIZZES ====================

    /**
     * Create a quiz
     */
    public function createQuiz(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5|max:180',
            'total_points' => 'required|integer|min:1|max:100',
            'passing_score' => 'required|integer|min:0',
            'max_attempts' => 'integer|min:1|max:10',
            'shuffle_questions' => 'boolean',
            'show_answers_after' => 'boolean',
            'proctoring_enabled' => 'boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|in:multiple_choice,true_false,short_answer,fill_blank,matching,ordering',
            'questions.*.question' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'required',
            'questions.*.points' => 'integer|min:1',
        ]);

        $teacher = $this->getTeacher($request);

        $quiz = Quiz::create([
            'course_id' => $request->course_id,
            'teacher_id' => $teacher->id,
            'title' => $request->title,
            'description' => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'total_points' => $request->total_points,
            'passing_score' => $request->passing_score,
            'max_attempts' => $request->max_attempts ?? 1,
            'shuffle_questions' => $request->shuffle_questions ?? false,
            'show_answers_after' => $request->show_answers_after ?? true,
            'proctoring_enabled' => $request->proctoring_enabled ?? false,
            'available_from' => $request->available_from,
            'available_until' => $request->available_until,
            'status' => 'draft',
        ]);

        // Create questions
        foreach ($request->questions as $index => $questionData) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'type' => $questionData['type'],
                'question' => $questionData['question'],
                'options' => $questionData['options'] ?? null,
                'correct_answer' => is_array($questionData['correct_answer']) 
                    ? $questionData['correct_answer'] 
                    : [$questionData['correct_answer']],
                'explanation' => $questionData['explanation'] ?? null,
                'points' => $questionData['points'] ?? 1,
                'order' => $index,
            ]);
        }

        return response()->json([
            'message' => 'Quiz créé avec succès',
            'quiz' => $quiz->load('questions'),
        ], 201);
    }

    /**
     * Get quizzes for a course
     */
    public function getCourseQuizzes(Request $request, $courseId)
    {
        $user = $request->user();
        
        $query = Quiz::with(['questions'])
            ->where('course_id', $courseId);

        if ($user->role === 'student') {
            // Students only see published quizzes
            $query->where('status', 'published');
        }

        $quizzes = $query->orderBy('created_at', 'desc')->get();

        // For students, add attempt info
        if ($user->role === 'student') {
            $quizzes = $quizzes->map(function ($quiz) use ($user) {
                // Count only COMPLETED attempts for the badge and limit check.
                // An in_progress attempt is still ongoing — the student should be
                // able to finish it without seeing "attempts exhausted".
                $completedAttempts = QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $user->student->id)
                    ->where('status', 'completed')
                    ->get();

                // Also check for a dangling in_progress attempt (e.g. tab closed
                // during the quiz). If one exists and we haven't exceeded the limit
                // it should not block the student from retrying.
                $hasInProgress = QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $user->student->id)
                    ->where('status', 'in_progress')
                    ->exists();

                $quiz->my_attempts   = $completedAttempts->count();
                $quiz->best_score    = $completedAttempts->max('score');
                $quiz->has_in_progress = $hasInProgress;
                $quiz->can_attempt   = $completedAttempts->count() < $quiz->max_attempts && $quiz->isAvailable();

                return $quiz;
            });
        }

        return response()->json(['quizzes' => $quizzes]);
    }

    /**
     * Start a quiz attempt
     */
    public function startQuiz(Request $request, $id)
    {
        $student = $this->getStudent($request);
        $quiz = Quiz::with('questions')->findOrFail($id);

        // Check if enrolled (through classes)
        if (!$this->isStudentEnrolledInCourse($student->id, $quiz->course_id)) {
            return response()->json(['error' => 'Vous n\'êtes pas inscrit à ce cours'], 403);
        }

        // Check availability
        if (!$quiz->isAvailable()) {
            return response()->json(['error' => 'Ce quiz n\'est pas disponible'], 403);
        }

        // Resume an existing in_progress attempt if present (e.g. student closed
        // the tab mid-quiz). This prevents double-counting toward max_attempts.
        $existingAttempt = QuizAttempt::where('quiz_id', $id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();

        if ($existingAttempt) {
            $attempt = $existingAttempt;
        } else {
            // Only count COMPLETED attempts toward the max_attempts limit.
            $completedCount = QuizAttempt::where('quiz_id', $id)
                ->where('student_id', $student->id)
                ->where('status', 'completed')
                ->count();

            if ($completedCount >= $quiz->max_attempts) {
                return response()->json(['error' => 'Nombre maximum de tentatives atteint'], 403);
            }

            // Create a fresh attempt
            $attempt = QuizAttempt::create([
                'quiz_id' => $id,
                'student_id' => $student->id,
                'started_at' => now(),
                'status' => 'in_progress',
            ]);
        }

        // Get questions (shuffle if needed)
        $questions = $quiz->shuffle_questions 
            ? $quiz->questions->shuffle() 
            : $quiz->questions;

        // Remove correct answers from response
        $questions = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'question' => $q->question,
                'options' => $q->options,
                'points' => $q->points,
            ];
        });

        return response()->json([
            'attempt_id' => $attempt->id,
            'quiz' => [
                'title' => $quiz->title,
                'duration_minutes' => $quiz->duration_minutes,
                'total_points' => $quiz->total_points,
                'proctoring_enabled' => $quiz->proctoring_enabled,
            ],
            'questions' => $questions,
            'started_at' => $attempt->started_at,
            'ends_at' => $attempt->started_at->addMinutes($quiz->duration_minutes),
        ]);
    }

    /**
     * Submit quiz answers
     */
    public function submitQuiz(Request $request, $attemptId)
    {
        $request->validate([
            'answers' => 'required|array',
        ]);

        $student = $this->getStudent($request);
        $attempt = QuizAttempt::where('id', $attemptId)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $attempt->answers = $request->answers;
        $attempt->completed_at = now();
        $attempt->status = 'completed';
        $attempt->save();

        // Calculate score
        $score = $attempt->calculateScore();

        $quiz = $attempt->quiz;
        $passed = $score >= $quiz->passing_score;

        $response = [
            'message' => $passed ? 'Quiz réussi!' : 'Quiz terminé',
            'score' => $score,
            'total_points' => $quiz->total_points,
            'passed' => $passed,
            'correct_count' => $attempt->correct_count,
            'total_questions' => $attempt->total_questions,
        ];

        // Add answers if show_answers_after is true
        if ($quiz->show_answers_after) {
            $response['answers'] = $quiz->questions->map(function ($q) use ($request) {
                return [
                    'question_id' => $q->id,
                    'your_answer' => $request->answers[$q->id] ?? null,
                    'correct_answer' => $q->correct_answer,
                    'is_correct' => $q->checkAnswer($request->answers[$q->id] ?? null),
                    'explanation' => $q->explanation,
                ];
            });
        }

        return response()->json($response);
    }

    /**
     * Report tab switch (proctoring)
     */
    public function reportTabSwitch(Request $request, $attemptId)
    {
        $student = $this->getStudent($request);
        $attempt = QuizAttempt::where('id', $attemptId)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $attempt->increment('tab_switches');

        return response()->json(['tab_switches' => $attempt->tab_switches]);
    }

    // ==================== ASSIGNMENTS ====================

    /**
     * Create an assignment
     */
    public function createAssignment(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'class_id' => 'nullable|exists:classes,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'nullable|string',
            'total_points' => 'required|integer|min:1|max:100',
            'due_date' => 'required|date|after:now',
            'allow_late_submission' => 'boolean',
            'late_penalty_percent' => 'integer|min:0|max:100',
            'allow_multiple_submissions' => 'boolean',
            'allowed_file_types' => 'nullable|array',
            'max_file_size_mb' => 'integer|min:1|max:100',
        ]);

        $teacher = $this->getTeacher($request);

        // If class specified, verify teacher owns the class and it belongs to the course
        if ($request->class_id) {
            $class = \App\Models\ClassModel::findOrFail($request->class_id);
            if ($class->teacher_id !== $teacher->id) {
                return response()->json(['error' => 'Accès refusé: vous n\'enseignez pas cette classe'], 403);
            }
            if ($class->course_id !== (int)$request->course_id) {
                return response()->json(['error' => 'La classe ne correspond pas au cours fourni'], 422);
            }
        }

        $assignment = Assignment::create([
            'class_id' => $request->class_id ?? null,
            'course_id' => $request->course_id,
            'teacher_id' => $teacher->id,
            'title' => $request->title,
            'description' => $request->description,
            'instructions' => $request->instructions,
            'total_points' => $request->total_points,
            'due_date' => $request->due_date,
            'allow_late_submission' => $request->allow_late_submission ?? false,
            'late_penalty_percent' => $request->late_penalty_percent ?? 10,
            'allow_multiple_submissions' => $request->allow_multiple_submissions ?? false,
            'allowed_file_types' => $request->allowed_file_types ?? ['pdf', 'doc', 'docx'],
            'max_file_size_mb' => $request->max_file_size_mb ?? 10,
            'status' => 'draft',
        ]);

        return response()->json([
            'message' => 'Devoir créé avec succès',
            'assignment' => $assignment,
        ], 201);
    }

    /**
     * Get assignments for a course
     */
    public function getCourseAssignments(Request $request, $courseId)
    {
        $user = $request->user();
        
        $query = Assignment::where('course_id', $courseId);

        if ($user->role === 'student') {
            // student: only published and class-specific logic
            $query->where('status', 'published');
            $classIds = $this->getStudentClassIdsForCourse($user->student->id, $courseId);

            $query->where(function ($q) use ($classIds) {
                $q->whereNull('class_id')->orWhereIn('class_id', $classIds);
            });
        }

        $assignments = $query->orderBy('due_date', 'asc')->get();

        // For students, add submission info
        if ($user->role === 'student') {
            $assignments = $assignments->map(function ($assignment) use ($user) {
                $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
                    ->where('student_id', $user->student->id)
                    ->first();
                
                $assignment->my_submission = $submission;
                $assignment->is_overdue = $assignment->isOverdue();
                $assignment->can_submit = $assignment->canSubmit() && 
                    (!$submission || $assignment->allow_multiple_submissions);
                
                return $assignment;
            });
        }

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Submit an assignment
     */
    public function submitAssignment(Request $request, $id)
    {
        $assignment = Assignment::findOrFail($id);
        $student = $this->getStudent($request);

        // Check enrollment: if assignment is targeted to a class, ensure student is in that class
        if ($assignment->class_id) {
            $isEnrolled = Enrollment::where('student_id', $student->id)
                ->where('class_id', $assignment->class_id)
                ->exists();
        } else {
            // Check through classes table
            $isEnrolled = $this->isStudentEnrolledInCourse($student->id, $assignment->course_id);
        }

        if (!$isEnrolled) {
            return response()->json(['error' => 'Vous n\'êtes pas inscrit à ce cours'], 403);
        }

        // Check if can submit
        if (!$assignment->canSubmit()) {
            return response()->json(['error' => 'Les soumissions ne sont plus acceptées'], 403);
        }

        // Check existing submission
        $existingSubmission = AssignmentSubmission::where('assignment_id', $id)
            ->where('student_id', $student->id)
            ->first();

        if ($existingSubmission && !$assignment->allow_multiple_submissions) {
            return response()->json(['error' => 'Vous avez déjà soumis ce devoir'], 403);
        }

        $request->validate([
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:' . ($assignment->max_file_size_mb * 1024),
        ]);

        $filePath = null;
        $fileName = null;
        $fileSize = 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Check allowed types
            if ($assignment->allowed_file_types && !in_array($extension, $assignment->allowed_file_types)) {
                return response()->json([
                    'error' => 'Type de fichier non autorisé. Types acceptés: ' . implode(', ', $assignment->allowed_file_types)
                ], 422);
            }

            $filePath = $file->store('assignments/' . $id, 'public');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
        }

        $submission = AssignmentSubmission::create([
            'assignment_id' => $id,
            'student_id' => $student->id,
            'content' => $request->content,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'is_late' => $assignment->isOverdue(),
            'status' => 'submitted',
        ]);

        return response()->json([
            'message' => 'Devoir soumis avec succès',
            'submission' => $submission,
        ], 201);
    }

    /**
     * Grade a submission (teacher)
     */
    public function gradeSubmission(Request $request, $submissionId)
    {
        $request->validate([
            'grade' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $submission = AssignmentSubmission::with('assignment')->findOrFail($submissionId);
        
        // Verify teacher owns the course
        $teacher = $this->getTeacher($request);
        if ($submission->assignment->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Validate grade
        if ($request->grade > $submission->assignment->total_points) {
            return response()->json([
                'error' => 'La note ne peut pas dépasser ' . $submission->assignment->total_points . ' points'
            ], 422);
        }

        $submission->update([
            'grade' => $request->grade,
            'feedback' => $request->feedback,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => 'Note enregistrée',
            'submission' => $submission,
            'final_grade' => $submission->calculateFinalGrade(),
        ]);
    }

    // ==================== ADDITIONAL TEACHER FEATURES ====================

    /**
     * Publish a quiz
     */
    public function publishQuiz(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);
        $teacher = $this->getTeacher($request);

        if ($quiz->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $quiz->update(['status' => 'published']);

        // Notify enrolled students
        $course = Course::find($quiz->course_id);
        $teacherName = $teacher->user->first_name ?? 'Un professeur';
        $enrolledUserIds = Enrollment::join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->where('classes.course_id', $quiz->course_id)
            ->where('enrollments.status', 'enrolled')
            ->pluck('students.user_id');

        foreach ($enrolledUserIds as $userId) {
            Notification::notify(
                $userId,
                'new_quiz',
                'Nouveau quiz disponible',
                $teacherName . ' a publié le quiz "' . $quiz->title . '" dans ' . ($course?->name ?? 'votre cours'),
                '/student/elearning',
                ['course_id' => $quiz->course_id, 'quiz_id' => $quiz->id]
            );
        }

        return response()->json([
            'message' => 'Quiz publié avec succès',
            'quiz' => $quiz,
        ]);
    }

    /**
     * Publish an assignment
     */
    public function publishAssignment(Request $request, $id)
    {
        $assignment = Assignment::findOrFail($id);
        $teacher = $this->getTeacher($request);

        if ($assignment->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $assignment->update(['status' => 'published']);

        // Notify enrolled students
        $course = Course::find($assignment->course_id);
        $teacherName = $teacher->user->first_name ?? 'Un professeur';
        $enrolledUserIds = Enrollment::join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->where('classes.course_id', $assignment->course_id)
            ->where('enrollments.status', 'enrolled')
            ->pluck('students.user_id');

        foreach ($enrolledUserIds as $userId) {
            Notification::notify(
                $userId,
                'new_assignment',
                'Nouveau devoir publié',
                $teacherName . ' a publié le devoir "' . $assignment->title . '" dans ' . ($course?->name ?? 'votre cours'),
                '/student/elearning',
                ['course_id' => $assignment->course_id, 'assignment_id' => $assignment->id]
            );
        }

        return response()->json([
            'message' => 'Devoir publié avec succès',
            'assignment' => $assignment,
        ]);
    }

    /**
     * Get quiz results/attempts (teacher)
     */
    public function getQuizResults(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $teacher = $this->getTeacher($request);

        if ($quiz->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Include ALL attempts (completed + in_progress) so the teacher can see
        // students who started the quiz but haven't submitted yet.
        // Stats are computed on completed attempts only.
        $allAttempts = QuizAttempt::with(['student.user'])
            ->where('quiz_id', $quizId)
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END")   // completed first
            ->orderBy('completed_at', 'desc')
            ->get();

        $attempts = $allAttempts->map(function ($attempt) {
            $student = $attempt->student;
            return [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'student' => [
                    'id' => $student?->id,
                    'name' => $student
                        ? (($student->user?->first_name ?? '') . ' ' . ($student->user?->last_name ?? ''))
                        : 'Étudiant inconnu',
                    'registration_number' => $student?->student_id ?? 'N/A',
                ],
                'score' => $attempt->score,           // null for in_progress
                'correct_count' => $attempt->correct_count,
                'total_questions' => $attempt->total_questions,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,  // null for in_progress
                'tab_switches' => $attempt->tab_switches,
            ];
        });

        // Stats computed only on completed attempts
        $completedAttempts = $allAttempts->where('status', 'completed');
        $count = $completedAttempts->count();
        $stats = [
            'total_attempts'   => $allAttempts->count(),
            'completed_count'  => $count,
            'in_progress_count'=> $allAttempts->where('status', 'in_progress')->count(),
            'average_score'    => $count > 0 ? round((float)$completedAttempts->avg('score'), 2) : 0,
            'highest_score'    => $count > 0 ? (float)$completedAttempts->max('score') : 0,
            'lowest_score'     => $count > 0 ? (float)$completedAttempts->min('score') : 0,
            'pass_rate'        => $count > 0
                ? round(($completedAttempts->where('score', '>=', $quiz->passing_score)->count() / $count) * 100, 1)
                : 0,
        ];

        return response()->json([
            'quiz' => $quiz,
            'attempts' => $attempts,
            'stats' => $stats,
        ]);
    }

    /**
     * Get assignment submissions (teacher)
     */
    public function getAssignmentSubmissions(Request $request, $assignmentId)
    {
        $assignment = Assignment::findOrFail($assignmentId);
        $teacher = $this->getTeacher($request);

        if ($assignment->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $submissions = AssignmentSubmission::with(['student.user'])
            ->where('assignment_id', $assignmentId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'student' => [
                        'id' => $submission->student->id,
                        'name' => $submission->student->user->first_name . ' ' . $submission->student->user->last_name,
                        'registration_number' => $submission->student->registration_number ?? $submission->student->student_id,
                    ],
                    'content' => $submission->content,
                    'file_name' => $submission->file_name,
                    'file_path' => $submission->file_path,
                    'file_size' => $submission->file_size,
                    'is_late' => $submission->is_late,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'status' => $submission->status,
                    'submitted_at' => $submission->created_at,
                    'graded_at' => $submission->graded_at,
                ];
            });

        // Count total enrolled students through classes
        $totalEnrolled = Enrollment::join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('classes.course_id', $assignment->course_id)
            ->distinct('enrollments.student_id')
            ->count('enrollments.student_id');

        $stats = [
            'total_enrolled' => $totalEnrolled,
            'total_submitted' => $submissions->count(),
            'total_graded' => $submissions->where('status', 'graded')->count(),
            'total_late' => $submissions->where('is_late', true)->count(),
            'average_grade' => $submissions->whereNotNull('grade')->avg('grade'),
        ];

        return response()->json([
            'assignment' => $assignment,
            'submissions' => $submissions,
            'stats' => $stats,
        ]);
    }

    /**
     * Download a submission file (teacher)
     */
    public function downloadSubmission(Request $request, $submissionId)
    {
        $submission = AssignmentSubmission::with('assignment')->findOrFail($submissionId);
        $teacher = $this->getTeacher($request);

        if ($submission->assignment->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        if (!$submission->file_path) {
            return response()->json(['error' => 'Pas de fichier joint'], 404);
        }

        return Storage::disk('public')->download($submission->file_path, $submission->file_name);
    }

    /**
     * Delete a course material (teacher)
     */
    public function deleteMaterial(Request $request, $id)
    {
        $material = CourseMaterial::findOrFail($id);
        $teacher = $this->getTeacher($request);

        if ($material->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Delete the file
        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json(['message' => 'Document supprimé avec succès']);
    }

    /**
     * Delete a quiz (teacher)
     */
    public function deleteQuiz(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);
        $teacher = $this->getTeacher($request);

        if ($quiz->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Delete related questions and attempts
        $quiz->questions()->delete();
        $quiz->attempts()->delete();
        $quiz->delete();

        return response()->json(['message' => 'Quiz supprimé avec succès']);
    }

    /**
     * Delete an assignment (teacher)
     */
    public function deleteAssignment(Request $request, $id)
    {
        $assignment = Assignment::findOrFail($id);
        $teacher = $this->getTeacher($request);

        if ($assignment->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Delete related submissions and their files
        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path) {
                Storage::disk('public')->delete($submission->file_path);
            }
        }
        $assignment->submissions()->delete();
        $assignment->delete();

        return response()->json(['message' => 'Devoir supprimé avec succès']);
    }

    /**
     * Get teacher's assigned classes/courses
     */
    public function getTeacherClasses(Request $request)
    {
        $teacher = $this->getTeacher($request);
        
        $classes = \App\Models\ClassModel::with(['course', 'enrollments'])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'course_id' => $class->course_id,
                    'course_name' => $class->course->name,
                    'course_code' => $class->course->code,
                    'enrolled_count' => $class->enrollments->count(),
                ];
            });

        return response()->json(['classes' => $classes]);
    }
}
