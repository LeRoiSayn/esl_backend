<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\FeeType;
use App\Models\StudentFee;
use App\Models\Notification;
use App\Models\Payment;
use Carbon\Carbon;

class FullTestDataSeeder extends Seeder
{
    private string $academicYear = '2025-2026';

    public function run(): void
    {
        $this->command->info('→ Creating fee types...');
        $this->seedFeeTypes();

        $this->command->info('→ Creating classes & schedules...');
        $classMap = $this->seedClasses();

        $this->command->info('→ Enrolling students + grades + retakes...');
        $this->seedEnrollmentsAndGrades($classMap);

        $this->command->info('→ Creating fees & payment scenarios...');
        $this->seedFinance();

        $this->command->info('→ Creating historical fees for L2/L3 students...');
        $this->seedHistoricalFinance();

        $this->command->info('→ Simulating grade submissions for complete classes...');
        $this->seedGradeSubmissions();

        $this->command->info('✓ Full test data seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. FEE TYPES
    // ──────────────────────────────────────────────────────────────────────────
    private function seedFeeTypes(): void
    {
        $types = [
            ['name' => 'Frais d\'inscription',    'amount' => 150000, 'category' => 'registration', 'is_mandatory' => true,  'level' => null],
            ['name' => 'Scolarité L1',             'amount' => 800000, 'category' => 'tuition',      'is_mandatory' => true,  'level' => 'L1'],
            ['name' => 'Scolarité L2',             'amount' => 900000, 'category' => 'tuition',      'is_mandatory' => true,  'level' => 'L2'],
            ['name' => 'Scolarité L3',             'amount' => 950000, 'category' => 'tuition',      'is_mandatory' => true,  'level' => 'L3'],
            ['name' => 'Frais de laboratoire',     'amount' => 75000,  'category' => 'other',        'is_mandatory' => false, 'level' => null],
            ['name' => 'Frais de bibliothèque',    'amount' => 25000,  'category' => 'other',        'is_mandatory' => false, 'level' => null],
        ];

        foreach ($types as $t) {
            FeeType::firstOrCreate(['name' => $t['name']], array_merge($t, ['description' => $t['name'], 'is_active' => true]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. CLASSES + SCHEDULES
    // ──────────────────────────────────────────────────────────────────────────
    private function seedClasses(): array
    {
        $classMap = []; // [course_id => class_id]

        // Map dept code → teacher IDs for that dept
        $teachersByDept = [];
        Teacher::with('user')->get()->each(function ($t) use (&$teachersByDept) {
            $deptId = $t->department_id;
            $teachersByDept[$deptId][] = $t->id;
        });

        $rooms = ['Amphi A', 'Amphi B', 'Salle 101', 'Salle 102', 'Salle 201', 'Labo 1', 'Labo 2'];
        $scheduleTemplates = [
            ['day' => 'monday',    'start' => '08:00', 'end' => '10:00'],
            ['day' => 'monday',    'start' => '10:30', 'end' => '12:30'],
            ['day' => 'tuesday',   'start' => '08:00', 'end' => '10:00'],
            ['day' => 'tuesday',   'start' => '14:00', 'end' => '16:00'],
            ['day' => 'wednesday', 'start' => '08:00', 'end' => '10:00'],
            ['day' => 'wednesday', 'start' => '10:30', 'end' => '12:30'],
            ['day' => 'thursday',  'start' => '08:00', 'end' => '10:00'],
            ['day' => 'thursday',  'start' => '14:00', 'end' => '16:00'],
            ['day' => 'friday',    'start' => '08:00', 'end' => '10:00'],
            ['day' => 'friday',    'start' => '10:30', 'end' => '12:30'],
        ];

        $courses = Course::where('is_active', true)->get();
        $schedIdx = 0;

        foreach ($courses as $course) {
            $deptId = $course->department_id;
            $teachers = $teachersByDept[$deptId] ?? [];
            if (empty($teachers)) continue;

            $teacherId = $teachers[$schedIdx % count($teachers)];
            $sched = $scheduleTemplates[$schedIdx % count($scheduleTemplates)];
            $room  = $rooms[$schedIdx % count($rooms)];

            $class = ClassModel::firstOrCreate(
                ['course_id' => $course->id, 'academic_year' => $this->academicYear],
                [
                    'teacher_id'    => $teacherId,
                    'section'       => 'A',
                    'room'          => $room,
                    'capacity'      => 40,
                    'semester'      => $course->semester,
                    'is_active'     => true,
                ]
            );

            // Schedule
            Schedule::firstOrCreate(
                ['class_id' => $class->id, 'day_of_week' => $sched['day']],
                [
                    'start_time'   => $sched['start'],
                    'end_time'     => $sched['end'],
                    'room'         => $room,
                    'midterm_date' => Carbon::parse(explode('-', $this->academicYear)[0] . '-12-15'),
                    'final_date'   => Carbon::parse(explode('-', $this->academicYear)[1] . '-05-20'),
                ]
            );

            $classMap[$course->id] = $class->id;
            $schedIdx++;
        }

        return $classMap;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. ENROLLMENTS + GRADES
    // ──────────────────────────────────────────────────────────────────────────
    private function seedEnrollmentsAndGrades(array $classMap): void
    {
        $students = Student::with('user')->get()->keyBy(fn($s) => $s->user->username ?? '');

        // Helper: enroll a student in a class and optionally add a grade
        $enroll = function (Student $student, int $classId, ?array $gradeData = null) {
            $enrollment = Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'class_id' => $classId],
                ['enrollment_date' => '2024-09-01', 'status' => 'enrolled']
            );

            if ($gradeData !== null) {
                $final = $gradeData['final'];
                $letter = match(true) {
                    $final >= 90 => 'A+', $final >= 80 => 'A', $final >= 70 => 'B',
                    $final >= 60 => 'C', $final >= 50 => 'D', default => 'F',
                };
                Grade::firstOrCreate(['enrollment_id' => $enrollment->id], [
                    'attendance_score'      => $gradeData['att']   ?? round($final * 0.9, 2),
                    'quiz_score'            => $gradeData['quiz']  ?? round($final * 0.95, 2),
                    'continuous_assessment' => $gradeData['ca']    ?? round($final * 0.92, 2),
                    'exam_score'            => $gradeData['exam']  ?? round($final * 1.02, 2),
                    'final_grade'           => $final,
                    'letter_grade'          => $letter,
                    'remarks'               => $gradeData['remarks'] ?? ($final >= 50 ? 'Admis' : 'Ajourné'),
                    'graded_by'             => 1,
                    'graded_at'             => now()->subMonths(2),
                    'validated_at'          => $final >= 50 ? now()->subMonth() : null,
                ]);
            }

            return $enrollment;
        };

        // Helper: get class_id for a course code
        $cls = function (string $code) use ($classMap): ?int {
            $course = Course::where('code', $code)->first();
            return $course ? ($classMap[$course->id] ?? null) : null;
        };

        // ── merveille1 : L1 MED — inscrite en S1, pas encore de notes ──────
        $s = $students['merveille1'] ?? null;
        if ($s) {
            foreach (['MED101','MED102','MED103','MED104'] as $code) {
                if ($cid = $cls($code)) $enroll($s, $cid); // no grade yet
            }
        }

        // ── darrel1 : L1 MED — inscrit en S1, partiellement noté ───────────
        $s = $students['darrel1'] ?? null;
        if ($s) {
            if ($cid = $cls('MED101')) $enroll($s, $cid, ['final' => 62]);
            if ($cid = $cls('MED102')) $enroll($s, $cid, ['final' => 55]);
            if ($cid = $cls('MED103')) $enroll($s, $cid); // en cours
            if ($cid = $cls('MED104')) $enroll($s, $cid); // en cours
        }

        // ── kevine1 : L1 INF — inscrite S1, pas encore de notes ─────────────
        $s = $students['kevine1'] ?? null;
        if ($s) {
            foreach (['INF101','INF102','INF103'] as $code) {
                if ($cid = $cls($code)) $enroll($s, $cid);
            }
        }

        // ── chancelle1 : L1 INF — inscrite S2, quelques notes S1 ───────────
        $s = $students['chancelle1'] ?? null;
        if ($s) {
            // S1 notes
            if ($cid = $cls('INF101')) $enroll($s, $cid, ['final' => 74]);
            if ($cid = $cls('INF102')) $enroll($s, $cid, ['final' => 68]);
            if ($cid = $cls('INF103')) $enroll($s, $cid, ['final' => 71]);
            // S2 en cours
            if ($cid = $cls('INF104')) $enroll($s, $cid);
            if ($cid = $cls('INF105')) $enroll($s, $cid);
        }

        // ── exauc1 : L2 MED — a L1 complet avec UN ECHEC + RATTRAPAGE ───────
        $s = $students['exauc1'] ?? null;
        if ($s) {
            // L1 S1 — a échoué MED103 (Chimie)
            if ($cid = $cls('MED101')) $enroll($s, $cid, ['final' => 72]);
            if ($cid = $cls('MED102')) $enroll($s, $cid, ['final' => 65]);
            if ($cid = $cls('MED103')) $enroll($s, $cid, ['final' => 38, 'remarks' => 'Ajourné — rattrapage requis']); // ECHEC
            if ($cid = $cls('MED104')) $enroll($s, $cid, ['final' => 58]);
            if ($cid = $cls('MED105')) $enroll($s, $cid, ['final' => 70]);
            // L1 S2
            if ($cid = $cls('MED106')) $enroll($s, $cid, ['final' => 78]);
            if ($cid = $cls('MED107')) $enroll($s, $cid, ['final' => 62]);
            if ($cid = $cls('MED108')) $enroll($s, $cid, ['final' => 55]);
            if ($cid = $cls('MED109')) $enroll($s, $cid, ['final' => 67]);
            // L1 S3 + RATTRAPAGE MED103 (section B — classe de rattrapage)
            if ($cid = $cls('MED110')) $enroll($s, $cid, ['final' => 61]);
            $retakeCourse103 = Course::where('code', 'MED103')->first();
            if ($retakeCourse103) {
                $retakeTeacher = Teacher::first();
                $retakeClass103 = ClassModel::firstOrCreate(
                    ['course_id' => $retakeCourse103->id, 'academic_year' => $this->academicYear, 'section' => 'B'],
                    ['teacher_id' => $retakeTeacher?->id, 'room' => 'Salle R1', 'capacity' => 20,
                     'semester' => $retakeCourse103->semester, 'is_active' => true]
                );
                $retakeEnrollment = Enrollment::firstOrCreate(
                    ['student_id' => $s->id, 'class_id' => $retakeClass103->id],
                    ['enrollment_date' => '2025-01-10', 'status' => 'enrolled']
                );
                Grade::firstOrCreate(['enrollment_id' => $retakeEnrollment->id], [
                    'final_grade' => 54, 'letter_grade' => 'D',
                    'remarks' => 'Rattrapage — Admis', 'graded_by' => 1,
                    'graded_at' => now()->subMonths(6), 'validated_at' => now()->subMonths(5),
                ]);
            }
            // L2 S1 en cours
            if ($cid = $cls('MED201')) $enroll($s, $cid);
            if ($cid = $cls('MED202')) $enroll($s, $cid);
            if ($cid = $cls('MED203')) $enroll($s, $cid);

            // Marquer MED103 comme cours à rattraper dans le profil
            $s->update(['retake_courses' => ['MED103']]);
        }

        // ── danielle1 : L2 MED — L1 complet avec bonnes notes ───────────────
        $s = $students['danielle1'] ?? null;
        if ($s) {
            $l1Grades = [
                'MED101'=>82,'MED102'=>76,'MED103'=>80,'MED104'=>71,'MED105'=>85,
                'MED106'=>79,'MED107'=>73,'MED108'=>68,'MED109'=>77,'MED110'=>83,
            ];
            foreach ($l1Grades as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            // L2 S1 avec quelques notes
            if ($cid = $cls('MED201')) $enroll($s, $cid, ['final' => 74]);
            if ($cid = $cls('MED202')) $enroll($s, $cid, ['final' => 69]);
            if ($cid = $cls('MED203')) $enroll($s, $cid, ['final' => 72]);
            if ($cid = $cls('MED204')) $enroll($s, $cid);
            if ($cid = $cls('MED205')) $enroll($s, $cid);
        }

        // ── joris1 : L2 INF — L1 complet, bon dossier ────────────────────────
        $s = $students['joris1'] ?? null;
        if ($s) {
            $l1 = ['INF101'=>75,'INF102'=>68,'INF103'=>72,'INF104'=>80,'INF105'=>65,'INF106'=>70,'INF107'=>78,'INF108'=>83];
            foreach ($l1 as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            // L2 en cours
            if ($cid = $cls('INF201')) $enroll($s, $cid);
            if ($cid = $cls('INF202')) $enroll($s, $cid);
        }

        // ── grce1 : L3 MED — L1+L2 avec 1 échec L2 rattrapé + cours L1 reporté en L3 ──
        $s = $students['grce1'] ?? null;
        if ($s) {
            $l1 = [
                'MED101'=>88,'MED102'=>82,'MED103'=>79,'MED104'=>75,'MED105'=>90,
                'MED106'=>85,'MED107'=>78,'MED108'=>72,'MED109'=>80,'MED110'=>77,
            ];
            foreach ($l1 as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            $l2 = [
                'MED201'=>76,'MED202'=>68,'MED204'=>72,'MED205'=>80,
                'MED206'=>65,'MED207'=>71,'MED208'=>74,'MED210'=>78,
            ];
            foreach ($l2 as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            // MED203 — échec en L2
            if ($cid = $cls('MED203')) $enroll($s, $cid, ['final' => 42, 'remarks' => 'Ajourné']);
            // MED209 — non validé L2, reporté en L3
            if ($cid = $cls('MED209')) $enroll($s, $cid, ['final' => 44, 'remarks' => 'Ajourné — cours reporté en L3']);
            // L3 en cours + MED203 en rattrapage (section B)
            if ($cid = $cls('MED301')) $enroll($s, $cid);
            if ($cid = $cls('MED302')) $enroll($s, $cid);
            $retakeCourse203 = Course::where('code', 'MED203')->first();
            if ($retakeCourse203) {
                $retakeTeacher = Teacher::first();
                $retakeClass203 = ClassModel::firstOrCreate(
                    ['course_id' => $retakeCourse203->id, 'academic_year' => $this->academicYear, 'section' => 'B'],
                    ['teacher_id' => $retakeTeacher?->id, 'room' => 'Salle R1', 'capacity' => 20,
                     'semester' => $retakeCourse203->semester, 'is_active' => true]
                );
                $retake = Enrollment::firstOrCreate(
                    ['student_id' => $s->id, 'class_id' => $retakeClass203->id],
                    ['enrollment_date' => '2025-09-10', 'status' => 'enrolled']
                );
                Grade::firstOrCreate(['enrollment_id' => $retake->id], [
                    'final_grade' => 58, 'letter_grade' => 'D',
                    'remarks' => 'Rattrapage L3 — Admis', 'graded_by' => 1,
                    'graded_at' => now()->subMonths(1),
                ]);
            }
            $s->update(['retake_courses' => ['MED203', 'MED209']]);
        }

        // ── christophe1 : L3 PHA ────────────────────────────────────────────
        $s = $students['christophe1'] ?? null;
        if ($s) {
            $l1 = ['PHA101'=>70,'PHA102'=>75,'PHA103'=>68,'PHA104'=>72,'PHA105'=>65,'PHA106'=>78,'PHA107'=>80,'PHA108'=>85];
            $l2 = ['PHA201'=>72,'PHA202'=>68,'PHA203'=>74,'PHA204'=>70,'PHA205'=>65,'PHA206'=>71];
            foreach (array_merge($l1, $l2) as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            if ($cid = $cls('PHA301')) $enroll($s, $cid);
            if ($cid = $cls('PHA302')) $enroll($s, $cid);
        }

        // ── melissa1 : L3 PHA — très bonne étudiante ────────────────────────
        $s = $students['melissa1'] ?? null;
        if ($s) {
            $l1 = ['PHA101'=>92,'PHA102'=>88,'PHA103'=>85,'PHA104'=>90,'PHA105'=>87,'PHA106'=>91,'PHA107'=>89,'PHA108'=>94];
            $l2 = ['PHA201'=>86,'PHA202'=>83,'PHA203'=>88,'PHA204'=>84,'PHA205'=>79,'PHA206'=>85];
            foreach (array_merge($l1, $l2) as $code => $g) {
                if ($cid = $cls($code)) $enroll($s, $cid, ['final' => $g]);
            }
            if ($cid = $cls('PHA301')) $enroll($s, $cid, ['final' => 88]);
            if ($cid = $cls('PHA302')) $enroll($s, $cid, ['final' => 85]);
            if ($cid = $cls('PHA303')) $enroll($s, $cid);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. FINANCE
    // ──────────────────────────────────────────────────────────────────────────
    private function seedFinance(): void
    {
        $regFee  = FeeType::where('category', 'registration')->first();
        $labFee  = FeeType::where('name', 'Frais de laboratoire')->first();
        $libFee  = FeeType::where('name', 'Frais de bibliothèque')->first();

        $students = Student::with('user')->get()->keyBy(fn($s) => $s->user->username ?? '');

        foreach ($students as $username => $student) {
            $tuitionFee = FeeType::where('category', 'tuition')
                ->where(fn($q) => $q->where('level', $student->level)->orWhereNull('level'))
                ->where('name', 'like', 'Scolarité ' . $student->level)
                ->first();

            $scenario = match($username) {
                // Tout payé comptant
                'melissa1', 'danielle1' => 'fully_paid',
                // Plan 3 mois, tout payé
                'christophe1' => 'installment_3_complete',
                // Plan 6 mois, en cours
                'grce1', 'joris1' => 'installment_6_partial',
                // Plan 3 mois, en retard
                'exauc1' => 'installment_3_overdue',
                // Acompte versé, reste dû
                'darrel1', 'chancelle1' => 'partial_payment',
                // Rien payé encore
                'merveille1', 'kevine1' => 'unpaid',
                default => 'partial_payment',
            };

            // Frais d'inscription (tous)
            if ($regFee) $this->createFeeWithPayment($student, $regFee, $scenario, 'registration');
            // Scolarité
            if ($tuitionFee) $this->createFeeWithPayment($student, $tuitionFee, $scenario, 'tuition');
            // Labo (L2+)
            if ($labFee && in_array($student->level, ['L2','L3'])) {
                $this->createFeeWithPayment($student, $labFee, 'fully_paid', 'lab');
            }
            // Bibliothèque
            if ($libFee) $this->createFeeWithPayment($student, $libFee, in_array($username, ['melissa1','danielle1','christophe1']) ? 'fully_paid' : 'unpaid', 'lib');
        }
    }

    /**
     * Create a Payment record without triggering the boot() event that auto-updates
     * StudentFee.paid_amount. The seeder pre-sets paid_amount correctly on StudentFee,
     * so the event would double-count every payment.
     */
    private function insertPayment(array $data): void
    {
        Payment::withoutEvents(fn () => Payment::create($data));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4b. HISTORICAL FINANCE (L2/L3 prior academic years)
    // ──────────────────────────────────────────────────────────────────────────
    private function seedHistoricalFinance(): void
    {
        $regFee = FeeType::where('category', 'registration')->first();
        $labFee = FeeType::where('name', 'Frais de laboratoire')->first();
        $libFee = FeeType::where('name', 'Frais de bibliothèque')->first();
        $l1Fee  = FeeType::where('name', 'Scolarité L1')->first();
        $l2Fee  = FeeType::where('name', 'Scolarité L2')->first();

        $students = Student::with('user')->get()->keyBy(fn($s) => $s->user->username ?? '');

        // ── L2 STUDENTS — need L1 year (2024-2025) ───────────────────────────
        $l2Students = [
            // Danielle: tout payé depuis la 1ère année
            'danielle1' => ['l1_scenario' => 'fully_paid'],
            // Joris: plan versements L1, partiellement réglé
            'joris1'    => ['l1_scenario' => 'installment_6_partial_hist'],
            // Exauc: difficultés paiement en L1 (n'a pas tout payé)
            'exauc1'    => ['l1_scenario' => 'partial_payment'],
        ];

        foreach ($l2Students as $username => $cfg) {
            $student = $students[$username] ?? null;
            if (!$student) continue;

            $y1 = '2024-2025';
            $d1 = Carbon::parse('2024-09-15');

            if ($regFee) $this->createFeeWithPayment($student, $regFee, $cfg['l1_scenario'], 'registration', $y1, $d1);
            if ($l1Fee)  $this->createFeeWithPayment($student, $l1Fee, $cfg['l1_scenario'], 'tuition', $y1, $d1);
            if ($libFee) $this->createFeeWithPayment($student, $libFee, $cfg['l1_scenario'] === 'fully_paid' ? 'fully_paid' : 'unpaid', 'lib', $y1, $d1);
        }

        // ── L3 STUDENTS — need L1 (2023-2024) and L2 (2024-2025) years ──────
        $l3Students = [
            // Melissa: excellente étudiante, tout payé depuis L1
            'melissa1'    => ['l1_scenario' => 'fully_paid',    'l2_scenario' => 'fully_paid'],
            // Christophe: régulier, tout payé
            'christophe1' => ['l1_scenario' => 'fully_paid',    'l2_scenario' => 'installment_3_complete_hist'],
            // Grce: L1 tout payé, L2 avec des difficultés (partiel)
            'grce1'       => ['l1_scenario' => 'fully_paid',    'l2_scenario' => 'partial_payment'],
        ];

        foreach ($l3Students as $username => $cfg) {
            $student = $students[$username] ?? null;
            if (!$student) continue;

            // L1 — année 2023-2024
            $y1 = '2023-2024';
            $d1 = Carbon::parse('2023-09-15');

            if ($regFee) $this->createFeeWithPayment($student, $regFee, $cfg['l1_scenario'], 'registration', $y1, $d1);
            if ($l1Fee)  $this->createFeeWithPayment($student, $l1Fee, $cfg['l1_scenario'], 'tuition', $y1, $d1);
            if ($libFee) $this->createFeeWithPayment($student, $libFee, $cfg['l1_scenario'] === 'fully_paid' ? 'fully_paid' : 'unpaid', 'lib', $y1, $d1);

            // L2 — année 2024-2025
            $y2 = '2024-2025';
            $d2 = Carbon::parse('2024-09-15');

            if ($regFee) $this->createFeeWithPayment($student, $regFee, $cfg['l2_scenario'], 'registration', $y2, $d2);
            if ($l2Fee)  $this->createFeeWithPayment($student, $l2Fee, $cfg['l2_scenario'], 'tuition', $y2, $d2);
            if ($labFee) $this->createFeeWithPayment($student, $labFee, 'fully_paid', 'lab', $y2, $d2);
            if ($libFee) $this->createFeeWithPayment($student, $libFee, in_array($cfg['l2_scenario'], ['fully_paid','installment_3_complete_hist']) ? 'fully_paid' : 'unpaid', 'lib', $y2, $d2);
        }
    }

    private function createFeeWithPayment(Student $student, FeeType $feeType, string $scenario, string $context, ?string $forYear = null, ?Carbon $forBaseDate = null): void
    {
        $amount   = (float) $feeType->amount;
        $year     = $forYear ?? $this->academicYear;
        $baseDate = $forBaseDate ? $forBaseDate->copy() : Carbon::parse('2025-09-15');

        // Skip if already exists
        if (StudentFee::where('student_id', $student->id)->where('fee_type_id', $feeType->id)->where('academic_year', $year)->exists()) return;

        switch ($scenario) {
            case 'fully_paid':
                $fee = StudentFee::create([
                    'student_id'   => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'       => $amount,       'paid_amount' => $amount,
                    'due_date'     => $baseDate,     'status'      => 'paid',
                    'academic_year'=> $year,
                ]);
                $this->insertPayment([
                    'student_fee_id'   => $fee->id,
                    'amount'           => $amount,
                    'payment_method'   => 'bank_transfer',
                    'reference_number' => 'REF-' . strtoupper(substr(md5($student->id . $feeType->id), 0, 8)),
                    'payment_date'     => $baseDate->copy()->addDays(rand(1, 5)),
                    'notes'            => 'Paiement intégral',
                ]);
                break;

            case 'installment_3_complete':
                $monthly = round($amount / 3, 2);
                $plan = $this->buildInstallmentPlan($amount, 3, $baseDate, true);
                $fee = StudentFee::create([
                    'student_id'      => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'          => $amount,       'paid_amount' => $amount,
                    'due_date'        => $baseDate,     'status'      => 'paid',
                    'academic_year'   => $year,         'installment_plan' => $plan,
                ]);
                for ($m = 0; $m < 3; $m++) {
                    $this->insertPayment([
                        'student_fee_id'   => $fee->id,
                        'amount'           => $m === 2 ? $amount - ($monthly * 2) : $monthly,
                        'payment_method'   => 'mobile_money',
                        'reference_number' => 'INST3-' . $student->id . '-' . $feeType->id . '-' . ($m + 1),
                        'payment_date'     => $baseDate->copy()->addMonths($m),
                        'notes'            => 'Tranche ' . ($m + 1) . '/3',
                    ]);
                }
                break;

            case 'installment_6_partial':
                $monthly = round($amount / 6, 2);
                $paidMonths = rand(2, 4);
                $paidAmount = round($monthly * $paidMonths, 2);
                $plan = $this->buildInstallmentPlan($amount, 6, $baseDate, false, $paidMonths);
                $fee = StudentFee::create([
                    'student_id'      => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'          => $amount,       'paid_amount' => $paidAmount,
                    'due_date'        => $baseDate,     'status'      => 'partial',
                    'academic_year'   => $year,         'installment_plan' => $plan,
                ]);
                for ($m = 0; $m < $paidMonths; $m++) {
                    $this->insertPayment([
                        'student_fee_id'   => $fee->id,
                        'amount'           => $monthly,
                        'payment_method'   => 'cash',
                        'reference_number' => 'INST6-' . $student->id . '-' . $feeType->id . '-' . ($m + 1),
                        'payment_date'     => $baseDate->copy()->addMonths($m),
                        'notes'            => 'Tranche ' . ($m + 1) . '/6',
                    ]);
                }
                break;

            case 'installment_3_overdue':
                $monthly = round($amount / 3, 2);
                $plan = $this->buildInstallmentPlan($amount, 3, $baseDate->copy()->subMonths(4), false, 1);
                $fee = StudentFee::create([
                    'student_id'      => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'          => $amount,       'paid_amount' => $monthly,
                    'due_date'        => $baseDate->copy()->subMonths(3), 'status' => 'overdue',
                    'academic_year'   => $year,         'installment_plan' => $plan,
                ]);
                $this->insertPayment([
                    'student_fee_id'   => $fee->id,
                    'amount'           => $monthly,
                    'payment_method'   => 'cash',
                    'reference_number' => 'LATE-' . $student->id . '-' . $feeType->id . '-1',
                    'payment_date'     => $baseDate->copy()->subMonths(4),
                    'notes'            => 'Tranche 1/3 — tranches 2 et 3 en retard',
                ]);
                break;

            case 'partial_payment':
                $paid = round($amount * (rand(20, 50) / 100), 2);
                $fee = StudentFee::create([
                    'student_id'   => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'       => $amount,       'paid_amount' => $paid,
                    'due_date'     => $baseDate,     'status'      => 'partial',
                    'academic_year'=> $year,
                ]);
                $this->insertPayment([
                    'student_fee_id'   => $fee->id,
                    'amount'           => $paid,
                    'payment_method'   => 'cash',
                    'reference_number' => 'PART-' . strtoupper(substr(md5($student->id . $feeType->id), 0, 8)),
                    'payment_date'     => $baseDate->copy()->addDays(rand(1, 10)),
                    'notes'            => 'Acompte — solde restant dû',
                ]);
                break;

            // Historical variant: installment 6 months, all settled (used for past years)
            case 'installment_6_partial_hist':
                $monthly = round($amount / 6, 2);
                $paidMonths = rand(3, 5); // mostly paid but not complete
                $paidAmount = round($monthly * $paidMonths, 2);
                $plan = $this->buildInstallmentPlan($amount, 6, $baseDate, false, $paidMonths);
                $fee = StudentFee::create([
                    'student_id'      => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'          => $amount,       'paid_amount' => $paidAmount,
                    'due_date'        => $baseDate,     'status'      => 'partial',
                    'academic_year'   => $year,         'installment_plan' => $plan,
                ]);
                for ($m = 0; $m < $paidMonths; $m++) {
                    $this->insertPayment([
                        'student_fee_id'   => $fee->id,
                        'amount'           => $monthly,
                        'payment_method'   => 'cash',
                        'reference_number' => 'HIST6-' . $student->id . '-' . $feeType->id . '-' . $year . '-' . ($m + 1),
                        'payment_date'     => $baseDate->copy()->addMonths($m),
                        'notes'            => 'Tranche ' . ($m + 1) . '/6 — ' . $year,
                    ]);
                }
                break;

            // Historical variant: installment 3 months complete (used for past years)
            case 'installment_3_complete_hist':
                $monthly = round($amount / 3, 2);
                $plan = $this->buildInstallmentPlan($amount, 3, $baseDate, true);
                $fee = StudentFee::create([
                    'student_id'      => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'          => $amount,       'paid_amount' => $amount,
                    'due_date'        => $baseDate,     'status'      => 'paid',
                    'academic_year'   => $year,         'installment_plan' => $plan,
                ]);
                for ($m = 0; $m < 3; $m++) {
                    $this->insertPayment([
                        'student_fee_id'   => $fee->id,
                        'amount'           => $m === 2 ? $amount - ($monthly * 2) : $monthly,
                        'payment_method'   => 'mobile_money',
                        'reference_number' => 'HIST3-' . $student->id . '-' . $feeType->id . '-' . $year . '-' . ($m + 1),
                        'payment_date'     => $baseDate->copy()->addMonths($m),
                        'notes'            => 'Tranche ' . ($m + 1) . '/3 — ' . $year,
                    ]);
                }
                break;

            case 'unpaid':
            default:
                StudentFee::create([
                    'student_id'   => $student->id, 'fee_type_id' => $feeType->id,
                    'amount'       => $amount,       'paid_amount' => 0,
                    'due_date'     => $baseDate,     'status'      => 'pending',
                    'academic_year'=> $year,
                ]);
                break;
        }
    }

    private function buildInstallmentPlan(float $total, int $months, Carbon $start, bool $allPaid, int $paidCount = 0): array
    {
        $monthly = round($total / $months, 2);
        $tranches = [];
        for ($i = 0; $i < $months; $i++) {
            $tranches[] = [
                'month'      => $i + 1,
                'due_date'   => $start->copy()->addMonths($i)->format('Y-m-d'),
                'amount'     => $i === $months - 1 ? round($total - ($monthly * ($months - 1)), 2) : $monthly,
                'paid'       => $allPaid || $i < $paidCount,
                'paid_date'  => ($allPaid || $i < $paidCount) ? $start->copy()->addMonths($i)->format('Y-m-d') : null,
            ];
        }
        return ['months' => $months, 'monthly_amount' => $monthly, 'tranches' => $tranches];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. GRADE SUBMISSIONS (simulate teacher submit for fully-graded classes)
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * For every active class where ALL enrolled students have a grade record,
     * create a grades_submitted notification so the admin can see them as
     * "Soumis" and validate them.  Classes with missing grades stay "En attente".
     */
    private function seedGradeSubmissions(): void
    {
        $adminUser = User::where('role', 'admin')->first();
        $adminId   = $adminUser?->id ?? 1;

        $classes = ClassModel::with(['course', 'teacher.user'])
            ->where('is_active', true)
            ->get();

        foreach ($classes as $class) {
            $totalEnrolled = Enrollment::where('class_id', $class->id)
                ->where('status', 'enrolled')
                ->count();

            if ($totalEnrolled === 0) continue;

            $graded = Grade::whereHas(
                'enrollment',
                fn($q) => $q->where('class_id', $class->id)
            )->count();

            // Only submit if ALL enrolled students have a grade
            if ($graded < $totalEnrolled) continue;

            // Skip if a submission notification already exists for this class
            $alreadySubmitted = Notification::where('type', 'grades_submitted')
                ->whereJsonContains('data->class_id', $class->id)
                ->exists();

            if ($alreadySubmitted) continue;

            $courseName = $class->course?->name ?? ('Cours ' . $class->id);
            $teacherId  = $class->teacher?->user?->id ?? $adminId;
            $submittedAt = Carbon::now()->subWeeks(2);

            // Notification to admin
            Notification::create([
                'user_id'    => $adminId,
                'type'       => 'grades_submitted',
                'title'      => 'Notes soumises',
                'message'    => "Les notes de {$courseName} ont été soumises et sont en attente de validation.",
                'link'       => '/admin/grades',
                'read_at'    => null,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
                'data'       => [
                    'class_id'    => $class->id,
                    'course_name' => $courseName,
                    'submitted_by'=> $teacherId,
                ],
            ]);

            // Notification to teacher (for their notification feed)
            if ($teacherId !== $adminId) {
                Notification::create([
                    'user_id'    => $teacherId,
                    'type'       => 'grades_submitted',
                    'title'      => 'Notes soumises',
                    'message'    => "Vous avez soumis les notes de {$courseName} à l'administration.",
                    'link'       => '/teacher/grades',
                    'read_at'    => $submittedAt, // already read by teacher
                    'created_at' => $submittedAt,
                    'updated_at' => $submittedAt,
                    'data'       => [
                        'class_id'    => $class->id,
                        'course_name' => $courseName,
                        'submitted_by'=> $teacherId,
                    ],
                ]);
            }
        }
    }
}
