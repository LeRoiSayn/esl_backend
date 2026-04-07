<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user based on their role
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $result = \Illuminate\Support\Facades\Cache::remember(
            "notifications_user_{$user->id}",
            90, // 90 seconds — longer than the 60s frontend poll, so the second poll is always a cache hit
            function () use ($user) {
                $notifications = [];

                switch ($user->role) {
                    case 'student':
                        $notifications = $this->getStudentNotifications($user);
                        break;
                    case 'teacher':
                        $notifications = $this->getTeacherNotifications($user);
                        break;
                    case 'admin':
                        $notifications = $this->getAdminNotifications($user);
                        break;
                    case 'finance':
                        $notifications = $this->getFinanceNotifications($user);
                        break;
                    case 'registrar':
                        $notifications = $this->getRegistrarNotifications($user);
                        break;
                }

                // Sort by priority (high first) then by date (newest first)
                usort($notifications, function ($a, $b) {
                    $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
                    $pa = $priorityOrder[$a['priority']] ?? 2;
                    $pb = $priorityOrder[$b['priority']] ?? 2;
                    if ($pa !== $pb) return $pa - $pb;
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                return [
                    'notifications' => array_slice($notifications, 0, 20),
                    'unread_count'  => count(array_filter($notifications, fn($n) => !($n['read'] ?? false))),
                ];
            }
        );

        return response()->json($result);
    }

    /**
     * Delete a single persistent notification (DB-stored, id = numeric).
     */
    public function destroy(Request $request, int $id)
    {
        $deleted = \App\Models\Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        \Illuminate\Support\Facades\Cache::forget("notifications_user_{$request->user()->id}");

        return response()->json(['message' => 'Notification supprimée.']);
    }

    /**
     * Delete all persistent notifications for the authenticated user.
     */
    public function destroyAll(Request $request)
    {
        \App\Models\Notification::where('user_id', $request->user()->id)->delete();
        \Illuminate\Support\Facades\Cache::forget("notifications_user_{$request->user()->id}");

        return response()->json(['message' => 'Toutes les notifications ont été supprimées.']);
    }

    // ==================== STUDENT NOTIFICATIONS ====================

    private function getStudentNotifications($user)
    {
        $student = $user->student;
        if (!$student) return [];

        $notifications = [];

        // 1. Upcoming payment deadlines
        $upcomingFees = StudentFee::with('feeType')
            ->where('student_id', $student->id)
            ->where('status', '!=', 'paid')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(60))
            ->orderBy('due_date')
            ->get();

        foreach ($upcomingFees as $fee) {
            $daysLeft = now()->diffInDays($fee->due_date, false);
            $balance = $fee->amount - $fee->paid_amount;

            $notifications[] = [
                'id' => 'fee_' . $fee->id,
                'type' => 'payment_deadline',
                'icon' => 'currency',
                'title' => 'Échéance de paiement',
                'message' => ($fee->feeType->name ?? 'Frais') . ' - ' . number_format($balance) . ' FCFA à payer. Échéance dans ' . $daysLeft . ' jour(s).',
                'priority' => $daysLeft <= 7 ? 'high' : 'medium',
                'date' => $fee->due_date->toIso8601String(),
                'read' => false,
            ];
        }

        // 2. Overdue fees (status overdue OR unpaid with past due date)
        $overdueFees = StudentFee::with('feeType')
            ->where('student_id', $student->id)
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdueFees as $fee) {
            $balance = $fee->amount - $fee->paid_amount;
            $notifications[] = [
                'id' => 'overdue_' . $fee->id,
                'type' => 'overdue_payment',
                'icon' => 'exclamation',
                'title' => 'Paiement en retard',
                'message' => ($fee->feeType->name ?? 'Frais') . ' - ' . number_format($balance) . ' FCFA en retard de paiement.',
                'priority' => 'high',
                'date' => $fee->due_date->toIso8601String(),
                'read' => false,
            ];
        }

        // 3. Quiz deadlines (upcoming quizzes)
        $enrolledClassIds = Enrollment::where('student_id', $student->id)
            ->where('status', 'enrolled')
            ->pluck('class_id');

        $enrolledCourseIds = ClassModel::whereIn('id', $enrolledClassIds)->pluck('course_id');

        $upcomingQuizzes = \App\Models\Quiz::whereIn('course_id', $enrolledCourseIds)
            ->where('status', 'published')
            ->where('available_until', '>=', now())
            ->where('available_until', '<=', now()->addDays(7))
            ->orderBy('available_until')
            ->get();

        foreach ($upcomingQuizzes as $quiz) {
            $daysLeft = now()->diffInDays($quiz->available_until, false);
            $notifications[] = [
                'id' => 'quiz_' . $quiz->id,
                'type' => 'quiz_deadline',
                'icon' => 'academic',
                'title' => 'Quiz à compléter',
                'message' => $quiz->title . ' - disponible jusqu\'à ' . max(0, $daysLeft) . ' jour(s).',
                'priority' => $daysLeft <= 2 ? 'high' : 'medium',
                'date' => $quiz->available_until->toIso8601String(),
                'read' => false,
            ];
        }

        // 4. Assignment deadlines
        $upcomingAssignments = \App\Models\Assignment::whereIn('course_id', $enrolledCourseIds)
            ->where('status', 'published')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->orderBy('due_date')
            ->get();

        foreach ($upcomingAssignments as $assignment) {
            $daysLeft = now()->diffInDays($assignment->due_date, false);
            $notifications[] = [
                'id' => 'assignment_' . $assignment->id,
                'type' => 'assignment_deadline',
                'icon' => 'document',
                'title' => 'Devoir à rendre',
                'message' => $assignment->title . ' - à rendre dans ' . max(0, $daysLeft) . ' jour(s).',
                'priority' => $daysLeft <= 2 ? 'high' : 'medium',
                'date' => $assignment->due_date->toIso8601String(),
                'read' => false,
            ];
        }

        // 5. New grades published
        $recentGrades = Grade::with(['enrollment.class.course'])
            ->whereHas('enrollment', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($recentGrades as $grade) {
            $courseName = $grade->enrollment?->class?->course?->name ?? 'Cours';
            $notifications[] = [
                'id' => 'grade_' . $grade->id,
                'type' => 'grade_published',
                'icon' => 'chart',
                'title' => 'Nouvelle note publiée',
                'message' => $courseName . ' - Note finale: ' . $grade->final_grade . '/100',
                'priority' => 'low',
                'date' => $grade->created_at->toIso8601String(),
                'read' => false,
            ];
        }

        // 6. Today's schedule reminder
        $today = strtolower(now()->format('l'));
        $todaySchedules = Schedule::with(['class.course'])
            ->whereIn('class_id', $enrolledClassIds)
            ->where('day_of_week', $today)
            ->orderBy('start_time')
            ->get();

        if ($todaySchedules->count() > 0) {
            $courseList = $todaySchedules->map(fn($s) => ($s->class?->course?->name ?? 'Cours') . ' à ' . ($s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : substr($s->start_time, 0, 5)))->implode(', ');
            $notifications[] = [
                'id' => 'schedule_today',
                'type' => 'schedule_reminder',
                'icon' => 'calendar',
                'title' => "Cours aujourd'hui",
                'message' => $todaySchedules->count() . ' cours programmé(s): ' . $courseList,
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 7. New course materials (last 7 days)
        $recentMaterials = \App\Models\CourseMaterial::with('course')
            ->whereIn('course_id', $enrolledCourseIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($recentMaterials as $material) {
            $notifications[] = [
                'id' => 'material_' . $material->id,
                'type' => 'new_material',
                'icon' => 'document',
                'title' => 'Nouveau document partagé',
                'message' => ($material->course?->name ?? 'Cours') . ' — ' . $material->title,
                'priority' => 'medium',
                'date' => $material->created_at->toIso8601String(),
                'read' => false,
            ];
        }

        // 8. Upcoming online course sessions (next 7 days)
        $upcomingOnlineCourses = \App\Models\OnlineCourse::with('course')
            ->whereIn('course_id', $enrolledCourseIds)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        foreach ($upcomingOnlineCourses as $session) {
            $daysLeft = now()->diffInDays($session->scheduled_at, false);
            $notifications[] = [
                'id' => 'online_' . $session->id,
                'type' => 'online_session',
                'icon' => 'video',
                'title' => 'Cours en ligne programmé',
                'message' => ($session->course?->name ?? 'Cours') . ' — ' . $session->title . ' le ' . ($session->scheduled_at instanceof \Carbon\Carbon ? $session->scheduled_at->format('d/m/Y à H:i') : $session->scheduled_at),
                'priority' => $daysLeft <= 1 ? 'high' : 'medium',
                'date' => ($session->scheduled_at instanceof \Carbon\Carbon ? $session->scheduled_at->toIso8601String() : $session->scheduled_at),
                'read' => false,
            ];
        }

        // 9. Recently published assignments (last 7 days, not yet in deadline window)
        $newAssignments = \App\Models\Assignment::whereIn('course_id', $enrolledCourseIds)
            ->where('status', 'published')
            ->where('created_at', '>=', now()->subDays(7))
            ->where('due_date', '>', now()->addDays(7)) // avoid duplicating deadline notifications
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($newAssignments as $assignment) {
            $notifications[] = [
                'id' => 'new_assignment_' . $assignment->id,
                'type' => 'new_assignment',
                'icon' => 'document',
                'title' => 'Nouveau devoir publié',
                'message' => $assignment->title . ' — à rendre le ' . (\Carbon\Carbon::parse($assignment->due_date)->format('d/m/Y')),
                'priority' => 'medium',
                'date' => $assignment->created_at->toIso8601String(),
                'read' => false,
            ];
        }

        // 10. Recently published quizzes (last 7 days, not yet in deadline window)
        $newQuizzes = \App\Models\Quiz::whereIn('course_id', $enrolledCourseIds)
            ->where('status', 'published')
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($q) {
                $q->whereNull('available_until')
                  ->orWhere('available_until', '>', now()->addDays(7));
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($newQuizzes as $quiz) {
            $notifications[] = [
                'id' => 'new_quiz_' . $quiz->id,
                'type' => 'new_quiz',
                'icon' => 'academic',
                'title' => 'Nouveau quiz disponible',
                'message' => $quiz->title . ($quiz->available_until ? ' — disponible jusqu\'au ' . (\Carbon\Carbon::parse($quiz->available_until)->format('d/m/Y')) : ''),
                'priority' => 'medium',
                'date' => $quiz->created_at->toIso8601String(),
                'read' => false,
            ];
        }

        return $notifications;
    }

    // ==================== TEACHER NOTIFICATIONS ====================

    private function getTeacherNotifications($user)
    {
        $teacher = $user->teacher;
        if (!$teacher) return [];

        $notifications = [];

        // 1. Get teacher's classes (single query — both id and course_id)
        $teacherClasses = ClassModel::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->get(['id', 'course_id']);

        $classIds  = $teacherClasses->pluck('id');
        $courseIds = $teacherClasses->pluck('course_id');

        // 2. Pending assignment submissions to review
        $pendingSubmissions = \App\Models\AssignmentSubmission::whereHas('assignment', function ($q) use ($courseIds) {
            $q->whereIn('course_id', $courseIds);
        })->where('status', 'submitted')
            ->count();

        if ($pendingSubmissions > 0) {
            $notifications[] = [
                'id' => 'pending_submissions',
                'type' => 'submissions_pending',
                'icon' => 'document',
                'title' => 'Devoirs à corriger',
                'message' => $pendingSubmissions . ' soumission(s) de devoirs en attente de correction.',
                'priority' => 'medium',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 3. Today's classes
        $today = strtolower(now()->format('l'));
        $todaySchedules = Schedule::with(['class.course'])
            ->whereIn('class_id', $classIds)
            ->where('day_of_week', $today)
            ->orderBy('start_time')
            ->get();

        if ($todaySchedules->count() > 0) {
            $courseList = $todaySchedules->map(fn($s) => ($s->class?->course?->name ?? 'Cours') . ' à ' . ($s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : substr($s->start_time, 0, 5)))->implode(', ');
            $notifications[] = [
                'id' => 'schedule_today',
                'type' => 'schedule_reminder',
                'icon' => 'calendar',
                'title' => "Cours aujourd'hui",
                'message' => $todaySchedules->count() . ' cours programmé(s): ' . $courseList,
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 4. Students enrolled count
        $enrolledCount = Enrollment::whereIn('class_id', $classIds)
            ->where('status', 'enrolled')
            ->count();

        $notifications[] = [
            'id' => 'enrolled_students',
            'type' => 'info',
            'icon' => 'users',
            'title' => 'Étudiants inscrits',
            'message' => $enrolledCount . ' étudiant(s) inscrit(s) dans vos cours.',
            'priority' => 'low',
            'date' => now()->toIso8601String(),
            'read' => true,
        ];

        // 5. Quiz results available
        $quizzesWithResults = \App\Models\QuizAttempt::whereHas('quiz', function ($q) use ($courseIds) {
            $q->whereIn('course_id', $courseIds);
        })->where('created_at', '>=', now()->subDays(3))
            ->count();

        if ($quizzesWithResults > 0) {
            $notifications[] = [
                'id' => 'quiz_results',
                'type' => 'quiz_results',
                'icon' => 'chart',
                'title' => 'Résultats de quiz',
                'message' => $quizzesWithResults . ' tentative(s) de quiz récente(s) à consulter.',
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 6. Grade validation/rejection notifications (persistent, from DB)
        $gradeNotifications = \App\Models\Notification::where('user_id', $user->id)
            ->whereIn('type', ['grades_validated', 'grades_rejected'])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        foreach ($gradeNotifications as $notif) {
            $notifications[] = [
                'id'       => 'notif_' . $notif->id,
                'type'     => $notif->type,
                'icon'     => $notif->type === 'grades_validated' ? 'check' : 'exclamation',
                'title'    => $notif->title,
                'message'  => $notif->message,
                'priority' => $notif->type === 'grades_rejected' ? 'high' : 'medium',
                'date'     => $notif->created_at->toIso8601String(),
                'read'     => $notif->read_at !== null,
                'link'     => $notif->link,
                'data'     => $notif->data,
            ];
        }

        return $notifications;
    }

    // ==================== ADMIN NOTIFICATIONS ====================

    private function getAdminNotifications($user)
    {
        $notifications = [];

        // 1. System overview
        $totalStudents = Student::where('status', 'active')->count();
        $totalTeachers = \App\Models\Teacher::count();

        $notifications[] = [
            'id' => 'system_overview',
            'type' => 'info',
            'icon' => 'users',
            'title' => 'Système ESL',
            'message' => $totalStudents . ' étudiant(s) actif(s), ' . $totalTeachers . ' enseignant(s).',
            'priority' => 'low',
            'date' => now()->toIso8601String(),
            'read' => true,
        ];

        // 2. Overdue payments count
        $overdueCount = StudentFee::where('status', 'overdue')->count();
        if ($overdueCount > 0) {
            $notifications[] = [
                'id' => 'overdue_payments',
                'type' => 'overdue_payment',
                'icon' => 'exclamation',
                'title' => 'Paiements en retard',
                'message' => $overdueCount . ' frais étudiant(s) en retard de paiement.',
                'priority' => 'high',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 3. Pending enrollments
        $pendingEnrollments = Enrollment::where('status', 'pending')->count();
        if ($pendingEnrollments > 0) {
            $notifications[] = [
                'id' => 'pending_enrollments',
                'type' => 'enrollment',
                'icon' => 'academic',
                'title' => 'Inscriptions en attente',
                'message' => $pendingEnrollments . ' inscription(s) en attente de validation.',
                'priority' => 'medium',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 4. Recent payments today (single query with conditional aggregation)
        $todayRow = DB::selectOne("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE payment_date::date = CURRENT_DATE
        ");
        $todayPayments = (int) ($todayRow->cnt ?? 0);
        $todayAmount   = (float) ($todayRow->total ?? 0);

        if ($todayPayments > 0) {
            $notifications[] = [
                'id' => 'today_payments',
                'type' => 'payment',
                'icon' => 'currency',
                'title' => "Paiements du jour",
                'message' => $todayPayments . ' paiement(s) reçu(s) pour un total de ' . number_format($todayAmount) . ' FCFA.',
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 5. New students this week
        $newStudents = Student::where('created_at', '>=', now()->subDays(7))->count();
        if ($newStudents > 0) {
            $notifications[] = [
                'id' => 'new_students',
                'type' => 'info',
                'icon' => 'users',
                'title' => 'Nouveaux étudiants',
                'message' => $newStudents . ' nouvel(aux) étudiant(s) cette semaine.',
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 6. Grade submissions from teachers (persistent, from DB)
        $gradeSubmissions = \App\Models\Notification::where('user_id', $user->id)
            ->where('type', 'grades_submitted')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        foreach ($gradeSubmissions as $notif) {
            $notifications[] = [
                'id'       => 'notif_' . $notif->id,
                'type'     => 'grades_submitted',
                'icon'     => 'academic',
                'title'    => $notif->title,
                'message'  => $notif->message,
                'priority' => 'high',
                'date'     => $notif->created_at->toIso8601String(),
                'read'     => $notif->read_at !== null,
                'link'     => '/admin/grades',
                'data'     => $notif->data,
            ];
        }

        return $notifications;
    }

    // ==================== FINANCE NOTIFICATIONS ====================

    private function getFinanceNotifications($user)
    {
        $notifications = [];

        // 1. Overdue student fees (single query — count + balance)
        $overdueRow = DB::selectOne("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount - paid_amount), 0) AS total_balance
            FROM student_fees
            WHERE status = 'overdue'
        ");
        $overdueCount  = (int)   ($overdueRow->cnt           ?? 0);
        $overdueAmount = (float) ($overdueRow->total_balance  ?? 0);

        if ($overdueCount > 0) {
            $notifications[] = [
                'id' => 'overdue_fees',
                'type' => 'overdue_payment',
                'icon' => 'exclamation',
                'title' => 'Frais impayés',
                'message' => $overdueCount . ' étudiant(s) avec des frais en retard. Total: ' . number_format($overdueAmount) . ' FCFA.',
                'priority' => 'high',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 2. Pending fees
        $pendingCount = StudentFee::where('status', 'pending')->count();
        if ($pendingCount > 0) {
            $notifications[] = [
                'id' => 'pending_fees',
                'type' => 'payment_deadline',
                'icon' => 'clock',
                'title' => 'Frais en attente',
                'message' => $pendingCount . ' frais en attente de paiement.',
                'priority' => 'medium',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 3. Today's payments (single query)
        $finTodayRow = DB::selectOne("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE payment_date::date = CURRENT_DATE
        ");
        $todayPayments = (int)   ($finTodayRow->cnt   ?? 0);
        $todayAmount   = (float) ($finTodayRow->total ?? 0);

        $notifications[] = [
            'id' => 'today_collections',
            'type' => 'payment',
            'icon' => 'currency',
            'title' => 'Encaissements du jour',
            'message' => $todayPayments . ' paiement(s) - Total: ' . number_format($todayAmount) . ' FCFA.',
            'priority' => 'low',
            'date' => now()->toIso8601String(),
            'read' => true,
        ];

        // 4. Upcoming deadlines this week
        $upcomingDeadlines = StudentFee::where('status', '!=', 'paid')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->count();

        if ($upcomingDeadlines > 0) {
            $notifications[] = [
                'id' => 'upcoming_deadlines',
                'type' => 'payment_deadline',
                'icon' => 'calendar',
                'title' => 'Échéances cette semaine',
                'message' => $upcomingDeadlines . ' échéance(s) de paiement dans les 7 prochains jours.',
                'priority' => 'medium',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        return $notifications;
    }

    // ==================== REGISTRAR NOTIFICATIONS ====================

    private function getRegistrarNotifications($user)
    {
        $notifications = [];

        // 1. Pending enrollments
        $pendingEnrollments = Enrollment::where('status', 'pending')->count();
        if ($pendingEnrollments > 0) {
            $notifications[] = [
                'id' => 'pending_enrollments',
                'type' => 'enrollment',
                'icon' => 'academic',
                'title' => 'Inscriptions en attente',
                'message' => $pendingEnrollments . ' inscription(s) en attente de validation.',
                'priority' => 'high',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        // 2. Active students count
        $activeStudents = Student::where('status', 'active')->count();
        $notifications[] = [
            'id' => 'active_students',
            'type' => 'info',
            'icon' => 'users',
            'title' => 'Étudiants actifs',
            'message' => $activeStudents . ' étudiant(s) actuellement actif(s).',
            'priority' => 'low',
            'date' => now()->toIso8601String(),
            'read' => true,
        ];

        // 3. New students this week
        $newStudents = Student::where('created_at', '>=', now()->subDays(7))->count();
        if ($newStudents > 0) {
            $notifications[] = [
                'id' => 'new_students_week',
                'type' => 'info',
                'icon' => 'users',
                'title' => 'Nouvelles inscriptions',
                'message' => $newStudents . ' nouvel(aux) étudiant(s) inscrit(s) cette semaine.',
                'priority' => 'low',
                'date' => now()->toIso8601String(),
                'read' => false,
            ];
        }

        return $notifications;
    }
}
