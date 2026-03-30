<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\ClassModel;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    /** Active language for this request (fr|en) */
    private string $language = 'fr';

    /** Return text in the active language */
    private function t(string $fr, string $en): string
    {
        return $this->language === 'en' ? $en : $fr;
    }

    /**
     * Process a chatbot message with role-based access control
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message'    => 'required|string|max:1000',
            'session_id' => 'nullable|string',
            'language'   => 'nullable|in:fr,en',
        ]);

        $this->language = $request->input('language', 'fr');

        $user = $request->user();
        $message = $request->message;
        $sessionId = $request->session_id ?? Str::uuid()->toString();

        try {
            // Get or create conversation
            $conversation = ChatbotConversation::firstOrCreate(
                ['user_id' => $user->id, 'session_id' => $sessionId],
                ['messages' => [], 'context' => ['role' => $user->role, 'language' => $this->language]]
            );

            // Add user message
            $conversation->addMessage('user', $message);

            // Process message based on user role
            $response = $this->processMessage($user, $message, $conversation);

            // Add bot response
            $conversation->addMessage('assistant', $response['message']);

            return response()->json([
                'session_id' => $sessionId,
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Chatbot error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'session_id' => $sessionId,
                'response' => [
                    'message' => $this->t(
                        'Je suis désolé, une erreur technique est survenue. Veuillez réessayer.',
                        'Sorry, a technical error occurred. Please try again.'
                    ),
                    'type' => 'error',
                ],
            ]);
        }
    }

    /**
     * Search for a student (Admin only)
     */
    public function searchStudent(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['admin'])) {
            return response()->json([
                'error' => 'Accès refusé',
                'message' => 'Vous n\'avez pas les permissions nécessaires pour rechercher des étudiants.',
            ], 403);
        }

        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = $request->query('query');

        $students = Student::with(['user', 'department.faculty', 'enrollments', 'fees'])
            ->where(function ($q) use ($query) {
                $q->whereHas('user', function ($uq) use ($query) {
                    $uq->where('first_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                })
                ->orWhere('student_id', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($student) {
                return $this->formatStudentData($student);
            });

        return response()->json(['students' => $students]);
    }

    /**
     * Get detailed student info with trends (Admin only)
     */
    public function getStudentDetails(Request $request, $id)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['admin'])) {
            return response()->json([
                'error' => 'Accès refusé',
                'message' => 'Vous n\'avez pas les permissions nécessaires.',
            ], 403);
        }

        $student = Student::with([
            'user', 
            'department.faculty', 
            'enrollments', 
            'fees',
        ])->findOrFail($id);

        return response()->json([
            'student' => $this->formatStudentData($student, true),
        ]);
    }

    /**
     * Get conversation history
     */
    public function getHistory(Request $request)
    {
        $conversations = ChatbotConversation::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    // ==================== PRIVATE METHODS ====================

    private function processMessage($user, $message, $conversation)
    {
        $lowerMessage = mb_strtolower($message);
        
        // Check for student search (Admin only)
        if ($user->role === 'admin' && $this->isStudentSearch($lowerMessage)) {
            return $this->handleStudentSearch($lowerMessage);
        }

        // Process based on role
        switch ($user->role) {
            case 'admin':
                return $this->processAdminMessage($user, $lowerMessage, $conversation);
            case 'teacher':
                return $this->processTeacherMessage($user, $lowerMessage, $conversation);
            case 'student':
                return $this->processStudentMessage($user, $lowerMessage, $conversation);
            case 'finance':
                return $this->processFinanceMessage($user, $lowerMessage, $conversation);
            case 'registrar':
                return $this->processRegistrarMessage($user, $lowerMessage, $conversation);
            default:
                return $this->getDefaultResponse();
        }
    }

    private function isStudentSearch($message)
    {
        $keywords = ['cherche', 'recherche', 'trouve', 'montre', 'infos', 'étudiant', 'student', 'reg', 'notes de'];
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function handleStudentSearch($message)
    {
        // Extract search term
        preg_match('/(?:cherche|recherche|trouve|montre|infos|notes de)\s+(?:l\'étudiant|étudiant|student)?\s*(.+)/i', $message, $matches);
        
        if (empty($matches[1])) {
            // Try to find registration number
            preg_match('/reg[-\s]?(\d+)/i', $message, $regMatches);
            if (!empty($regMatches[1])) {
                $searchTerm = $regMatches[0];
            } else {
                // Try to find any name-like words
                preg_match('/(?:student|étudiant)\s+(\w+)/i', $message, $nameMatches);
                if (!empty($nameMatches[1])) {
                    $searchTerm = $nameMatches[1];
                } else {
                    return [
                        'message' => "Je n'ai pas pu identifier l'étudiant. Veuillez préciser le nom ou le numéro d'inscription.\n\nExemple: \"Recherche étudiant Dupont\" ou \"Infos REG-001\"",
                        'type' => 'help',
                        'quick_actions' => ['Statistiques des étudiants', 'KPIs institutionnels'],
                    ];
                }
            }
        } else {
            $searchTerm = trim($matches[1]);
        }

        $students = Student::with(['user', 'department', 'fees'])
            ->where(function ($q) use ($searchTerm) {
                $q->whereHas('user', function ($uq) use ($searchTerm) {
                    $uq->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%");
                })
                ->orWhere('student_id', 'like', "%{$searchTerm}%");
            })
            ->limit(5)
            ->get();

        if ($students->isEmpty()) {
            return [
                'message' => "Aucun étudiant trouvé pour \"{$searchTerm}\".\n\nEssayez avec un autre nom ou numéro d'inscription.",
                'type' => 'not_found',
                'quick_actions' => ['Statistiques des étudiants', 'KPIs institutionnels'],
            ];
        }

        if ($students->count() === 1) {
            $student = $students->first();
            $data = $this->formatStudentData($student, true);
            
            return [
                'message' => $this->formatStudentReport($data),
                'type' => 'student_info',
                'data' => $data,
            ];
        }

        // Multiple students found
        $list = $students->map(function ($s) {
            return "- {$s->user->first_name} {$s->user->last_name} ({$s->student_id})";
        })->join("\n");

        return [
            'message' => "Plusieurs étudiants trouvés:\n{$list}\n\nVeuillez préciser le nom complet ou le numéro d'inscription.",
            'type' => 'multiple_results',
            'data' => $students->map(fn($s) => $this->formatStudentData($s)),
        ];
    }

    private function processAdminMessage($user, $message, $conversation)
    {
        // Statistics queries
        if (str_contains($message, 'statistique') || str_contains($message, 'stats') || str_contains($message, 'global statistics') || str_contains($message, 'statistics')) {
            return $this->getAdminStatistics($message);
        }

        // KPI queries
        if (str_contains($message, 'kpi') || str_contains($message, 'performance') || str_contains($message, 'indicateur')) {
            return $this->getKPIs();
        }

        // Grades / submission queries
        if (str_contains($message, 'note') || str_contains($message, 'soum') ||
            str_contains($message, 'valid') || str_contains($message, 'professeur') ||
            str_contains($message, 'enseignant') || str_contains($message, 'grade')) {
            return $this->getAdminGradesOverview();
        }

        // Alert queries
        if (str_contains($message, 'alerte') || str_contains($message, 'alert') || str_contains($message, 'retard')) {
            return $this->getStudentAlerts();
        }

        // Help
        return [
            'message' => $this->t(
                "En tant qu'administrateur, voici ce que je peux faire:\n\n"
                . "🔍 **Rechercher un étudiant**\n   → \"Recherche étudiant Dupont\"\n\n"
                . "📊 **Statistiques globales**\n   → \"Montre les statistiques\"\n\n"
                . "📈 **KPIs institutionnels**\n   → \"KPIs\" ou \"Performance\"\n\n"
                . "⚠️ **Alertes**\n   → \"Alertes\" ou \"Étudiants en retard\"\n\n"
                . "📝 **Notes soumises**\n   → \"Notes des professeurs\" ou \"Validations\"\n\n"
                . 'Que souhaitez-vous faire?',
                "As administrator, here is what I can do:\n\n"
                . "🔍 **Search student**\n   → \"Search for student Smith\"\n\n"
                . "📊 **Global statistics**\n   → \"Show statistics\"\n\n"
                . "📈 **Institutional KPIs**\n   → \"KPIs\" or \"Performance\"\n\n"
                . "⚠️ **Alerts**\n   → \"Alerts\" or \"Students behind\"\n\n"
                . "📝 **Submitted grades**\n   → \"Teacher grades\" or \"Validations\"\n\n"
                . 'What would you like to do?'
            ),
            'type' => 'help',
            'quick_actions' => [
                ['label' => $this->t('📊 Statistiques', '📊 Statistics'), 'action' => 'show_kpis'],
                ['label' => $this->t('⚠️ Alertes', '⚠️ Alerts'), 'action' => 'show_alerts'],
                ['label' => $this->t('📝 Notes soumises', '📝 Submitted grades'), 'action' => 'show_grades'],
                ['label' => $this->t('🔍 Rechercher étudiant', '🔍 Search student'), 'action' => 'search_student'],
            ],
        ];
    }

    private function processTeacherMessage($user, $message, $conversation)
    {
        $teacher = $user->teacher;
        
        if (!$teacher) {
            return [
                'message' => $this->t(
                    "Votre profil enseignant n'est pas encore configuré. Veuillez contacter l'administration.",
                    'Your teacher profile is not configured yet. Please contact administration.'
                ),
                'type' => 'error',
            ];
        }

        // Grade entry help
        if (str_contains($message, 'saisir') || str_contains($message, 'enter grades') || str_contains($message, 'grade book') || str_contains($message, 'carnet de notes')) {
            return [
                'message' => $this->t(
                    "📝 **Comment saisir les notes:**\n\n"
                    . "1. Allez dans **Notes** (menu latéral)\n"
                    . "2. Sélectionnez une classe\n"
                    . "3. Saisissez les notes pour chaque étudiant:\n"
                    . "   • Présence: /10\n"
                    . "   • Quiz: /20\n"
                    . "   • Contrôle Continu (CC): /30\n"
                    . "   • Examen Final: /40\n"
                    . "4. Le total /100 est calculé automatiquement\n"
                    . "5. Cliquez **Enregistrer** puis **Soumettre à l'admin**\n\n"
                    . "✅ Le passage est à 50/100.",
                    "📝 **How to enter grades:**\n\n"
                    . "1. Go to **Grades** (sidebar menu)\n"
                    . "2. Select a class\n"
                    . "3. Enter scores for each student:\n"
                    . "   • Attendance: /10\n"
                    . "   • Quiz: /20\n"
                    . "   • Continuous Assessment (CA): /30\n"
                    . "   • Final Exam: /40\n"
                    . "4. The /100 total is calculated automatically\n"
                    . "5. Click **Save** then **Submit to admin**\n\n"
                    . "✅ Passing grade is 50/100."
                ),
                'type' => 'help',
                'quick_actions' => [
                    ['label' => $this->t('📚 Mes cours', 'My courses'), 'action' => 'my_courses'],
                ],
            ];
        }

        if (str_contains($message, 'mes cours') || str_contains($message, 'my courses') || str_contains($message, 'cours') || str_contains($message, 'courses')) {
            $classes = ClassModel::with('course')
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->get();

            if ($classes->isEmpty()) {
                return [
                    'message' => $this->t(
                        "Vous n'avez aucun cours assigné pour le moment.",
                        'You have no courses assigned at the moment.'
                    ),
                    'type' => 'courses',
                ];
            }

            $list = $classes->map(fn($c) => "- {$c->course->name} ({$c->course->code}) - {$c->name}")->join("\n");
            $label = $this->t('📚 Vos cours', '📚 Your courses');
            $total = $this->t('Total', 'Total');
            
            return [
                'message' => "{$label}:\n{$list}\n\n{$total}: {$classes->count()} " . $this->t('classe(s)', 'class(es)'),
                'type' => 'courses',
                'data' => $classes,
            ];
        }

        if (str_contains($message, 'étudiant') || str_contains($message, 'students') || str_contains($message, 'students') || str_contains($message, 'inscrit')) {
            $classIds = ClassModel::where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->pluck('id');
            
            $enrolledCount = Enrollment::whereIn('class_id', $classIds)
                ->where('status', 'enrolled')
                ->count();

            return [
                'message' => $this->t(
                    "👥 Vous avez {$enrolledCount} étudiant(s) inscrit(s) dans vos cours.",
                    "👥 You have {$enrolledCount} enrolled student(s) across your courses."
                ),
                'type' => 'info',
            ];
        }

        if (str_contains($message, 'emploi') || str_contains($message, 'schedule') || str_contains($message, 'horaire')) {
            $classIds = ClassModel::where('teacher_id', $teacher->id)->where('is_active', true)->pluck('id');
            $schedules = Schedule::with('class.course')
                ->whereIn('class_id', $classIds)
                ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 WHEN 'saturday' THEN 6 ELSE 7 END")
                ->orderBy('start_time')
                ->get();

            if ($schedules->isEmpty()) {
                return [
                    'message' => "Aucun horaire n'est encore défini pour vos cours. L'administration doit créer l'emploi du temps.",
                    'type' => 'schedule',
                ];
            }

            $list = $schedules->map(fn($s) => "- " . ucfirst($s->day_of_week) . ": {$s->class->course->name} ({$s->start_time} - {$s->end_time})" . ($s->room ? " - Salle {$s->room}" : ""))->join("\n");

            return [
                'message' => "📅 Votre emploi du temps:\n{$list}",
                'type' => 'schedule',
            ];
        }

        if (str_contains($message, 'absent') || str_contains($message, 'présence')) {
            return [
                'message' => "Pour gérer les présences, accédez à la section **Présence** dans le menu latéral.\n\nVous pouvez y:\n- Marquer les présences par classe\n- Voir les statistiques de présence\n- Générer des rapports",
                'type' => 'redirect',
            ];
        }

        return [
            'message' => $this->t(
                "Bonjour {$user->first_name}! 🎓\n\nEn tant qu'enseignant, je peux vous aider avec:\n\n"
                . "📚 **Mes cours** → Voir vos cours assignés\n"
                . "👥 **Mes étudiants** → Nombre d'étudiants inscrits\n"
                . "📅 **Mon emploi du temps** → Vos horaires\n"
                . "📝 **Saisir les notes** → Carnet de notes\n\n"
                . "Que souhaitez-vous faire?",
                "Hello {$user->first_name}! 🎓\n\nAs teacher, I can help you with:\n\n"
                . "📚 **My courses** → View your assigned courses\n"
                . "👥 **My students** → Number of enrolled students\n"
                . "📅 **My schedule** → Your timetable\n"
                . "📝 **Enter grades** → Grade book\n\n"
                . 'What would you like to do?'
            ),
            'type' => 'help',
            'quick_actions' => [
                ['label' => $this->t('📚 Mes cours', '📚 My courses'), 'action' => 'my_courses'],
                ['label' => $this->t('👥 Mes étudiants', '👥 My students'), 'action' => 'my_students'],
                ['label' => $this->t('📅 Mon emploi du temps', '📅 My schedule'), 'action' => 'my_schedule'],
                ['label' => $this->t('📝 Saisir les notes', '📝 Enter grades'), 'action' => 'my_grades_entry'],
            ],
        ];
    }

    private function processStudentMessage($user, $message, $conversation)
    {
        $student = $user->student;
        
        if (!$student) {
            return [
                'message' => "Votre profil étudiant n'est pas encore configuré. Veuillez contacter l'administration.",
                'type' => 'error',
            ];
        }

        if (str_contains($message, 'notes') || str_contains($message, 'grades') || str_contains($message, 'résultat')) {
            $grades = Grade::with('enrollment.class.course')
                ->whereHas('enrollment', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                })
                ->get();

            if ($grades->isEmpty()) {
                return [
                    'message' => "Aucune note n'est encore disponible pour vous.",
                    'type' => 'grades',
                ];
            }

            $average = $grades->avg('final_grade');
            $list = $grades->map(fn($g) => "- " . ($g->enrollment?->class?->course?->name ?? 'Cours') . ": {$g->final_grade}/100 ({$g->letter_grade})")->join("\n");

            return [
                'message' => "📊 Vos notes:\n{$list}\n\n📈 Moyenne générale: " . round($average, 2) . "/100",
                'type' => 'grades',
                'data' => ['grades' => $grades, 'average' => $average],
            ];
        }

        if (str_contains($message, 'frais') || str_contains($message, 'payer') || str_contains($message, 'fees') || str_contains($message, 'scolarité')) {
            $fees = $student->fees;
            $totalFees = $fees->sum('amount');
            $totalPaid = $fees->sum('paid_amount');
            $remaining = $totalFees - $totalPaid;
            
            return [
                'message' => "💰 Situation financière:\n\n" .
                    "├── Frais totaux: " . number_format($totalFees) . " FCFA\n" .
                    "├── Payé: " . number_format($totalPaid) . " FCFA\n" .
                    "└── Reste: " . number_format($remaining) . " FCFA\n\n" .
                    ($remaining > 0 ? "⚠️ Il vous reste " . number_format($remaining) . " FCFA à payer.\nAccédez à la page **Paiement** pour effectuer un versement." : "✅ Tous vos frais sont réglés!"),
                'type' => 'fees',
                'data' => ['total' => $totalFees, 'paid' => $totalPaid, 'remaining' => $remaining],
            ];
        }

        if (str_contains($message, 'emploi') || str_contains($message, 'schedule') || str_contains($message, 'horaire')) {
            $enrolledClassIds = Enrollment::where('student_id', $student->id)
                ->where('status', 'enrolled')
                ->pluck('class_id');

            $schedules = Schedule::with('class.course')
                ->whereIn('class_id', $enrolledClassIds)
                ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 WHEN 'saturday' THEN 6 ELSE 7 END")
                ->orderBy('start_time')
                ->get();

            if ($schedules->isEmpty()) {
                return [
                    'message' => "Aucun horaire n'est encore défini pour vos cours. L'emploi du temps sera publié par l'administration.",
                    'type' => 'schedule',
                ];
            }

            $list = $schedules->map(fn($s) => "- " . ucfirst($s->day_of_week) . ": " . ($s->class?->course?->name ?? 'Cours') . " ({$s->start_time} - {$s->end_time})" . ($s->room ? " - Salle {$s->room}" : ""))->join("\n");

            return [
                'message' => "📅 Votre emploi du temps:\n{$list}",
                'type' => 'schedule',
            ];
        }

        if (str_contains($message, 'cours') || str_contains($message, 'courses') || str_contains($message, 'inscrit')) {
            $enrollments = Enrollment::with('class.course')
                ->where('student_id', $student->id)
                ->where('status', 'enrolled')
                ->get();

            if ($enrollments->isEmpty()) {
                return [
                    'message' => "Vous n'êtes inscrit(e) à aucun cours pour le moment.",
                    'type' => 'courses',
                ];
            }

            $list = $enrollments->map(fn($e) => "- " . ($e->class?->course?->name ?? 'Cours') . " (" . ($e->class?->course?->code ?? '') . ")")->join("\n");

            return [
                'message' => "📚 Vos cours inscrits:\n{$list}\n\nTotal: {$enrollments->count()} cours",
                'type' => 'courses',
            ];
        }

        return [
            'message' => $this->t(
                "Bonjour {$user->first_name}! 👋\n\nJe suis Simon, votre assistant ESL. Je peux vous aider avec:\n\n"
                . "📊 **Mes notes** → Consulter vos résultats\n"
                . "💰 **Mes frais** → Situation financière\n"
                . "📅 **Mon emploi du temps** → Horaires de cours\n"
                . "📚 **Mes cours** → Cours inscrits\n\n"
                . 'Que souhaitez-vous savoir?',
                "Hello {$user->first_name}! 👋\n\nI'm Simon, your ESL assistant. I can help you with:\n\n"
                . "📊 **My grades** → View your results\n"
                . "💰 **My fees** → Financial status\n"
                . "📅 **My timetable** → Course schedule\n"
                . "📚 **My courses** → Enrolled courses\n\n"
                . 'What would you like to know?'
            ),
            'type' => 'help',
            'quick_actions' => [
                ['label' => $this->t('📊 Mes notes', '📊 My grades'), 'action' => 'my_grades'],
                ['label' => $this->t('💰 Mes frais', '💰 My fees'), 'action' => 'my_fees'],
                ['label' => $this->t('📅 Mon emploi du temps', '📅 Timetable'), 'action' => 'my_schedule'],
                ['label' => $this->t('📚 E-Learning', '📚 E-Learning'), 'action' => 'go_elearning'],
            ],
        ];
    }

    private function processFinanceMessage($user, $message, $conversation)
    {
        if (str_contains($message, 'impayé') || str_contains($message, 'retard') || str_contains($message, 'overdue')) {
            $overdueCount = StudentFee::where('status', 'overdue')->count();
            $overdueAmount = StudentFee::where('status', 'overdue')
                ->selectRaw('SUM(amount - paid_amount) as total_balance')
                ->value('total_balance') ?? 0;
            
            return [
                'message' => "💰 Rapport des impayés:\n\n" .
                    "├── Étudiants en retard: {$overdueCount}\n" .
                    "└── Montant total dû: " . number_format($overdueAmount) . " FCFA\n\n" .
                    "Accédez à **Frais Étudiants** pour voir les détails.",
                'type' => 'finance_stats',
            ];
        }

        if (str_contains($message, 'stat') || str_contains($message, "aujourd'hui") || str_contains($message, 'today')) {
            $todayPayments = Payment::whereDate('payment_date', today())->count();
            $todayAmount = Payment::whereDate('payment_date', today())->sum('amount');
            $totalCollected = Payment::sum('amount');

            return [
                'message' => "📊 Statistiques financières:\n\n" .
                    "📅 Aujourd'hui:\n" .
                    "├── Paiements: {$todayPayments}\n" .
                    "└── Montant: " . number_format($todayAmount) . " FCFA\n\n" .
                    "📈 Global:\n" .
                    "└── Total encaissé: " . number_format($totalCollected) . " FCFA",
                'type' => 'finance_stats',
            ];
        }

        if (str_contains($message, 'summary') || str_contains($message, 'résumé') || str_contains($message, 'global')) {
            $totalFees   = StudentFee::sum('amount');
            $totalPaid   = StudentFee::sum('paid_amount');
            $totalPending = StudentFee::where('status', 'pending')->count();
            $totalOverdue = StudentFee::where('status', 'overdue')->count();
            $totalPayments = Payment::sum('amount');

            return [
                'message' => $this->t(
                    "🧾 Résumé financier global:\n\n"
                    . "├── Frais totaux attribués: " . number_format($totalFees) . " FCFA\n"
                    . "├── Total encaissé: " . number_format($totalPaid) . " FCFA\n"
                    . "├── Paiements enregistrés: " . number_format($totalPayments) . " FCFA\n"
                    . "├── Dossiers en attente: {$totalPending}\n"
                    . "└── Dossiers en retard: {$totalOverdue}",
                    "🧾 Global Finance Summary:\n\n"
                    . "├── Total fees assigned: " . number_format($totalFees) . " FCFA\n"
                    . "├── Total collected: " . number_format($totalPaid) . " FCFA\n"
                    . "├── Payments recorded: " . number_format($totalPayments) . " FCFA\n"
                    . "├── Pending cases: {$totalPending}\n"
                    . "└── Overdue cases: {$totalOverdue}"
                ),
                'type' => 'finance_stats',
            ];
        }

        if (str_contains($message, 'rapport') || str_contains($message, 'report') || str_contains($message, 'mensuel') || str_contains($message, 'monthly')) {
            $monthPayments = Payment::whereMonth('payment_date', now()->month)->count();
            $monthAmount = Payment::whereMonth('payment_date', now()->month)->sum('amount');
            $pendingCount = StudentFee::where('status', 'pending')->count();

            return [
                'message' => "📈 Rapport mensuel (" . now()->format('F Y') . "):\n\n" .
                    "├── Paiements reçus: {$monthPayments}\n" .
                    "├── Montant total: " . number_format($monthAmount) . " FCFA\n" .
                    "└── Frais en attente: {$pendingCount}",
                'type' => 'finance_report',
            ];
        }

        return [
            'message' => $this->t(
                "En tant que gestionnaire financier, je peux vous aider avec:\n\n"
                . "💰 **Impayés** → Voir les paiements en retard\n"
                . "📊 **Stats du jour** → Statistiques d'aujourd'hui\n"
                . "📈 **Rapport mensuel** → Rapport du mois\n"
                . "🧾 **Résumé financier** → Vue d'ensemble globale\n\n"
                . 'Que souhaitez-vous faire?',
                "As finance manager, I can help you with:\n\n"
                . "💰 **Overdue payments** → View late payments\n"
                . "📊 **Today's stats** → Today's statistics\n"
                . "📈 **Monthly report** → This month's report\n"
                . "🧾 **Finance summary** → Global overview\n\n"
                . 'What would you like to do?'
            ),
            'type' => 'help',
            'quick_actions' => [
                ['label' => $this->t('💰 Impayés', '💰 Overdue payments'), 'action' => 'show_unpaid'],
                ['label' => $this->t("📊 Stats du jour", "📊 Today's stats"), 'action' => 'today_stats'],
                ['label' => $this->t('📈 Rapport mensuel', '📈 Monthly report'), 'action' => 'monthly_report'],
                ['label' => $this->t('🧾 Résumé financier', '🧾 Finance summary'), 'action' => 'finance_summary'],
            ],
        ];
    }

    private function processRegistrarMessage($user, $message, $conversation)
    {
        // Pending enrollments
        if (str_contains($message, 'pending') || str_contains($message, 'attente')) {
            $pendingEnrollments = Enrollment::where('status', 'pending')->count();
            return [
                'message' => $this->t(
                    "📋 Inscriptions en attente: **{$pendingEnrollments}**\n\nAccédez à **Étudiants** pour les traiter.",
                    "📋 Pending enrollments: **{$pendingEnrollments}**\n\nGo to **Students** to process them."
                ),
                'type' => 'registrar_stats',
            ];
        }

        // Active students
        if (str_contains($message, 'active') || str_contains($message, 'actif') || str_contains($message, 'combien')) {
            $activeStudents  = Student::where('status', 'active')->count();
            $totalStudents   = Student::count();
            $newThisMonth    = Student::whereMonth('created_at', now()->month)->count();
            return [
                'message' => $this->t(
                    "👥 Étudiants:\n"
                    . "├── Total: {$totalStudents}\n"
                    . "├── Actifs: {$activeStudents}\n"
                    . "└── Inscrits ce mois: {$newThisMonth}",
                    "👥 Students:\n"
                    . "├── Total: {$totalStudents}\n"
                    . "├── Active: {$activeStudents}\n"
                    . "└── Enrolled this month: {$newThisMonth}"
                ),
                'type' => 'registrar_stats',
            ];
        }

        // New enrollments
        if (str_contains($message, 'nouveau') || str_contains($message, 'new enrollment') || str_contains($message, 'nouveaux')) {
            $newStudents = Student::whereMonth('created_at', now()->month)
                ->with('user', 'department')
                ->limit(10)->get();
            if ($newStudents->isEmpty()) {
                return [
                    'message' => $this->t(
                        'Aucun nouvel étudiant enregistré ce mois.',
                        'No new students enrolled this month.'
                    ),
                    'type' => 'registrar_stats',
                ];
            }
            $list = $newStudents->map(fn($s) => '- ' . ($s->user->first_name ?? '') . ' ' . ($s->user->last_name ?? '') . ' (' . ($s->department->name ?? 'N/A') . ')')->join("\n");
            $label = $this->t('🎓 Nouveaux inscrits ce mois', '🎓 New enrollments this month');
            return [
                'message' => "{$label}:\n{$list}",
                'type' => 'registrar_stats',
            ];
        }

        // General stats
        if (str_contains($message, 'stat') || str_contains($message, 'inscript') || str_contains($message, 'enroll')) {
            $activeStudents     = Student::where('status', 'active')->count();
            $totalStudents      = Student::count();
            $pendingEnrollments = Enrollment::where('status', 'pending')->count();
            $enrolledCount      = Enrollment::where('status', 'enrolled')->count();

            return [
                'message' => $this->t(
                    "📊 Statistiques d'inscriptions:\n\n"
                    . "├── Étudiants actifs: {$activeStudents} / {$totalStudents}\n"
                    . "├── Inscriptions actives: {$enrolledCount}\n"
                    . "└── En attente: {$pendingEnrollments}\n\n"
                    . "Accédez à **Étudiants** pour gérer les inscriptions.",
                    "📊 Enrollment statistics:\n\n"
                    . "├── Active students: {$activeStudents} / {$totalStudents}\n"
                    . "├── Active enrollments: {$enrolledCount}\n"
                    . "└── Pending: {$pendingEnrollments}\n\n"
                    . 'Go to **Students** to manage enrollments.'
                ),
                'type' => 'registrar_stats',
            ];
        }

        // Student search by registrar
        if ($this->isStudentSearch(mb_strtolower($message))) {
            return $this->handleStudentSearch(mb_strtolower($message));
        }

        return [
            'message' => $this->t(
                "En tant que registraire, je peux vous aider avec:\n\n"
                . "📋 **Inscriptions en attente** → Traiter les nouvelles inscriptions\n"
                . "👥 **Étudiants actifs** → Nombre d'étudiants\n"
                . "📊 **Statistiques** → Vue d'ensemble des inscriptions\n"
                . "🎓 **Nouveaux inscrits** → Inscrits ce mois\n"
                . "🔍 **Recherche** → Cherche étudiant Dupont\n\n"
                . 'Que souhaitez-vous faire?',
                "As registrar, I can help you with:\n\n"
                . "📋 **Pending enrollments** → Process new registrations\n"
                . "👥 **Active students** → Student count\n"
                . "📊 **Statistics** → Enrollment overview\n"
                . "🎓 **New enrollments** → Enrolled this month\n"
                . "🔍 **Search** → Search for student Smith\n\n"
                . 'What would you like to do?'
            ),
            'type' => 'help',
            'quick_actions' => [
                ['label' => $this->t('📋 En attente', '📋 Pending'), 'action' => 'show_pending'],
                ['label' => $this->t('👥 Étudiants actifs', '👥 Active students'), 'action' => 'show_active_students'],
                ['label' => $this->t('📊 Statistiques', '📊 Statistics'), 'action' => 'show_registrar_stats'],
                ['label' => $this->t('🎓 Nouveaux inscrits', '🎓 New enrollments'), 'action' => 'show_new_enrollments'],
            ],
        ];
    }

    private function getDefaultResponse()
    {
        return [
            'message' => "Je suis Simon, votre assistant ESL. 👋\n\nComment puis-je vous aider?",
            'type' => 'default',
        ];
    }

    private function formatStudentData($student, $detailed = false)
    {
        $enrollmentIds = Enrollment::where('student_id', $student->id)->pluck('id');
        $grades = Grade::with('enrollment.class.course')
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get();
        $average = $grades->avg('final_grade');

        $fees = $student->fees ?? collect();
        $totalFees = $fees->sum('amount');
        $totalPaid = $fees->sum('paid_amount');
        $attendanceRate = $this->calculateAttendanceRate($student);

        $data = [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => $student->user->first_name . ' ' . $student->user->last_name,
            'email' => $student->user->email,
            'department' => $student->department->name ?? 'N/A',
            'level' => $student->level,
            'status' => $student->status,
            'average' => round($average ?? 0, 2),
            'attendance_rate' => $attendanceRate,
            'total_fees' => $totalFees,
            'paid' => $totalPaid,
            'remaining' => $totalFees - $totalPaid,
        ];

        if ($detailed) {
            $data['trend'] = 'stable';
            $data['grades'] = $grades->map(fn($g) => [
                'course' => $g->enrollment?->class?->course?->name ?? 'N/A',
                'grade' => $g->final_grade,
                'letter' => $g->letter_grade,
            ]);

            $enrollments = Enrollment::with('class.course')
                ->where('student_id', $student->id)
                ->where('status', 'enrolled')
                ->get();

            $data['enrollments'] = $enrollments->map(fn($e) => [
                'course' => $e->class?->course?->name ?? 'N/A',
                'code' => $e->class?->course?->code ?? '',
            ]);
        }

        return $data;
    }

    private function calculateAttendanceRate($student)
    {
        $enrollmentIds = Enrollment::where('student_id', $student->id)->pluck('id');
        $total = Attendance::whereIn('enrollment_id', $enrollmentIds)->count();
        $present = Attendance::whereIn('enrollment_id', $enrollmentIds)
            ->whereIn('status', ['present', 'late'])
            ->count();
        
        return $total > 0 ? round(($present / $total) * 100, 1) : 100;
    }

    private function calculateTrend($gradeHistory)
    {
        if ($gradeHistory->count() < 2) {
            return 'stable';
        }

        $first = $gradeHistory->first()->avg_grade;
        $last = $gradeHistory->last()->avg_grade;
        $diff = $last - $first;

        if ($diff > 0.5) return 'up';
        if ($diff < -0.5) return 'down';
        return 'stable';
    }

    private function formatStudentReport($data)
    {
        $trend = match($data['trend'] ?? 'stable') {
            'up' => '↗️ En progression',
            'down' => '↘️ En baisse',
            default => '→ Stable',
        };

        return "🎓 FICHE ÉTUDIANT - {$data['name']}\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "📋 Informations Générales\n" .
            "├── ID: {$data['student_id']}\n" .
            "├── Email: {$data['email']}\n" .
            "├── Département: {$data['department']}\n" .
            "├── Niveau: {$data['level']}\n" .
            "└── Statut: {$data['status']}\n\n" .
            "📊 Performance Académique\n" .
            "├── Moyenne Générale: {$data['average']}/100\n" .
            "└── Tendance: {$trend}\n\n" .
            "📅 Présences: {$data['attendance_rate']}%\n\n" .
            "💰 Situation Financière\n" .
            "├── Frais totaux: " . number_format($data['total_fees']) . " FCFA\n" .
            "├── Payé: " . number_format($data['paid']) . " FCFA\n" .
            "└── Reste: " . number_format($data['remaining']) . " FCFA";
    }

    private function getAdminStatistics($message)
    {
        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'active')->count();
        $avgGrade = Grade::avg('final_grade');
        $totalTeachers = \App\Models\Teacher::count();
        $totalCourses = Course::where('is_active', true)->count();

        return [
            'message' => "📊 Statistiques ESL\n\n" .
                "👥 Étudiants\n" .
                "├── Total: {$totalStudents}\n" .
                "└── Actifs: {$activeStudents}\n\n" .
                "🎓 Enseignants: {$totalTeachers}\n" .
                "📚 Cours actifs: {$totalCourses}\n\n" .
                "📈 Performance\n" .
                "└── Moyenne générale: " . round($avgGrade ?? 0, 2) . "/100",
            'type' => 'statistics',
        ];
    }

    private function getKPIs()
    {
        $totalStudents = Student::count();
        $totalPayments = Payment::sum('amount');
        $gradeCount = Grade::count();
        $passRate = $gradeCount > 0 ? (Grade::where('final_grade', '>=', 50)->count() / $gradeCount * 100) : 0;
        $attendanceAvg = Student::where('status', 'active')->get()->avg('attendance_rate') ?? 0;

        return [
            'message' => "📊 KPIs Institutionnels\n\n" .
                "├── Effectif total: {$totalStudents} étudiants\n" .
                "├── Revenus encaissés: " . number_format($totalPayments) . " FCFA\n" .
                "├── Taux de réussite: " . round($passRate, 1) . "%\n" .
                "└── Taux de présence moyen: " . round($attendanceAvg, 1) . "%",
            'type' => 'kpis',
        ];
    }

    private function getStudentAlerts()
    {
        $overdueCount = StudentFee::where('status', 'overdue')->count();
        $lowGradeStudents = Grade::join('enrollments', 'grades.enrollment_id', '=', 'enrollments.id')
            ->selectRaw('enrollments.student_id, AVG(grades.final_grade) as avg_grade')
            ->groupBy('enrollments.student_id')
            ->havingRaw('AVG(grades.final_grade) < 50')
            ->get()
            ->count();

        return [
            'message' => "⚠️ Alertes Étudiants\n\n" .
                "💰 Paiements en retard: {$overdueCount} étudiant(s)\n" .
                "📉 Moyenne < 10/20: {$lowGradeStudents} étudiant(s)\n\n" .
                "Accédez au module **Gestion des Étudiants** pour voir les détails.",
            'type' => 'alerts',
        ];
    }

    private function getAdminGradesOverview()
    {
        // Classes that have had grades submitted by their teacher
        $submittedNotifs = \App\Models\Notification::where('type', 'grades_submitted')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get unique class submissions
        $seen = [];
        $lines = [];
        foreach ($submittedNotifs as $notif) {
            $data = is_array($notif->data) ? $notif->data : json_decode($notif->data, true);
            $classId = $data['class_id'] ?? null;
            if ($classId && !isset($seen[$classId])) {
                $seen[$classId] = true;
                $course   = $data['course_name'] ?? 'Cours inconnu';
                $teacher  = $data['teacher'] ?? 'Enseignant';
                $graded   = $data['graded'] ?? '?';
                $total    = $data['total'] ?? '?';
                $lines[]  = "- {$course} | Prof: {$teacher} | {$graded}/{$total} étudiants notés";
            }
        }

        if (empty($lines)) {
            return [
                'message' => "📋 Aucune soumission de notes n'a été effectuée par les enseignants pour le moment.\n\n" .
                    "Les enseignants soumettent leurs notes depuis le module **Notes** → « Soumettre à l'administration ».",
                'type' => 'grades_overview',
            ];
        }

        $list  = implode("\n", $lines);
        $count = count($lines);

        return [
            'message' => "📝 Notes soumises par les enseignants ({$count} classe(s)):\n\n{$list}\n\n" .
                "➡️ Rendez-vous dans **Administration → Notes** pour valider ou modifier les notes.",
            'type'    => 'grades_overview',
            'quick_actions' => [
                ['label' => '📊 Voir les notes', 'action' => 'go_to_grades', 'url' => '/admin/grades'],
            ],
        ];
    }
}

