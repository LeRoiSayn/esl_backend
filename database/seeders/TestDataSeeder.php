<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Student;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Clear existing test data ────────────────────────────────────────
        $this->command->info('Clearing existing students and courses...');

        // Delete all student users (cascades to student, enrollments, fees, grades)
        User::where('role', 'student')->each(function ($user) {
            $user->delete();
        });

        // Delete all courses (cascades to classes, enrollments, grades, schedules)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('grades')->truncate();
        DB::table('attendance')->truncate();
        DB::table('enrollments')->truncate();
        DB::table('schedules')->truncate();
        DB::table('classes')->truncate();
        DB::table('courses')->truncate();
        DB::table('departments')->truncate();
        DB::table('faculties')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Cleared.');

        // ── 2. Create 3 Faculties + Departments ────────────────────────────────
        $facultyData = [
            [
                'faculty' => [
                    'name'        => 'Faculté de Médecine',
                    'code'        => 'FMED',
                    'description' => 'Formation en médecine générale et spécialisée',
                    'dean_name'   => 'Prof. Jean-Baptiste Mboumba',
                    'is_active'   => true,
                ],
                'department' => [
                    'name'      => 'Sciences Médicales',
                    'code'      => 'MED',
                    'head_name' => 'Dr. Henriette Nzé',
                    'is_active' => true,
                ],
                'prefix' => 'MED',
                'courses' => [
                    // L1 - S1 (4 courses, 2 tronc commun)
                    ['code' => 'MED101', 'name' => 'Anatomie Générale I',              'level' => 'L1', 'sem' => '1', 'cr' => 6, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'MED102', 'name' => 'Biologie Cellulaire',              'level' => 'L1', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC101',  'name' => 'Informatique Médicale',            'level' => 'L1', 'sem' => '1', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    ['code' => 'TC102',  'name' => 'Anglais Médical I',               'level' => 'L1', 'sem' => '1', 'cr' => 2, 'h' => 2, 'type' => 'tronc_commun'],
                    // L1 - S2 (3 courses, 1 tronc commun)
                    ['code' => 'MED106', 'name' => 'Anatomie Générale II',             'level' => 'L1', 'sem' => '2', 'cr' => 6, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'MED107', 'name' => 'Histologie et Embryologie',        'level' => 'L1', 'sem' => '2', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC103',  'name' => 'Biostatistique',                   'level' => 'L1', 'sem' => '2', 'cr' => 4, 'h' => 3, 'type' => 'tronc_commun'],
                    // L1 - S3 (2 courses, 1 tronc commun)
                    ['code' => 'MED110', 'name' => 'Physiologie Générale',             'level' => 'L1', 'sem' => '3', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC104',  'name' => 'Méthodologie de la Recherche',     'level' => 'L1', 'sem' => '3', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    // L2 - S1 (1 course)
                    ['code' => 'MED201', 'name' => 'Microbiologie Médicale',           'level' => 'L2', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    // L2 - S2 (1 course, tronc commun)
                    ['code' => 'TC105',  'name' => 'Anglais Médical II',               'level' => 'L2', 'sem' => '2', 'cr' => 2, 'h' => 2, 'type' => 'tronc_commun'],
                    // L3 - S1 (1 course)
                    ['code' => 'MED301', 'name' => 'Médecine Interne I',               'level' => 'L3', 'sem' => '1', 'cr' => 6, 'h' => 5, 'type' => 'specialisation'],
                ],
            ],
            [
                'faculty' => [
                    'name'        => 'Faculté des Sciences Infirmières',
                    'code'        => 'FSI',
                    'description' => 'Formation en soins infirmiers et pratiques cliniques',
                    'dean_name'   => 'Prof. Marie-Claire Obame',
                    'is_active'   => true,
                ],
                'department' => [
                    'name'      => 'Sciences Infirmières',
                    'code'      => 'INF',
                    'head_name' => 'Dr. Patrick Ella',
                    'is_active' => true,
                ],
                'prefix' => 'INF',
                'courses' => [
                    // L1 - S1 (4 courses, 2 tronc commun)
                    ['code' => 'INF101', 'name' => 'Soins Infirmiers Fondamentaux I',  'level' => 'L1', 'sem' => '1', 'cr' => 6, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'INF102', 'name' => 'Anatomie et Physiologie',          'level' => 'L1', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC201',  'name' => 'Informatique de Santé',            'level' => 'L1', 'sem' => '1', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    ['code' => 'TC202',  'name' => 'Communication en Santé',           'level' => 'L1', 'sem' => '1', 'cr' => 2, 'h' => 2, 'type' => 'tronc_commun'],
                    // L1 - S2 (3 courses, 1 tronc commun)
                    ['code' => 'INF106', 'name' => 'Soins Infirmiers Fondamentaux II', 'level' => 'L1', 'sem' => '2', 'cr' => 6, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'INF107', 'name' => 'Pharmacologie Infirmière',         'level' => 'L1', 'sem' => '2', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC203',  'name' => 'Éthique et Déontologie',           'level' => 'L1', 'sem' => '2', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    // L1 - S3 (2 courses, 1 tronc commun)
                    ['code' => 'INF110', 'name' => 'Stage Clinique I',                 'level' => 'L1', 'sem' => '3', 'cr' => 6, 'h' => 5, 'type' => 'specialisation'],
                    ['code' => 'TC204',  'name' => 'Recherche en Sciences Infirmières','level' => 'L1', 'sem' => '3', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    // L2 - S1 (1 course)
                    ['code' => 'INF201', 'name' => 'Soins Spécialisés I',              'level' => 'L2', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    // L2 - S2 (1 course, tronc commun)
                    ['code' => 'TC205',  'name' => 'Santé Communautaire',              'level' => 'L2', 'sem' => '2', 'cr' => 4, 'h' => 3, 'type' => 'tronc_commun'],
                    // L3 - S1 (1 course)
                    ['code' => 'INF301', 'name' => 'Soins Intensifs et Réanimation',   'level' => 'L3', 'sem' => '1', 'cr' => 6, 'h' => 5, 'type' => 'specialisation'],
                ],
            ],
            [
                'faculty' => [
                    'name'        => 'Faculté de Santé Publique',
                    'code'        => 'FSP',
                    'description' => 'Formation en épidémiologie, santé communautaire et gestion de la santé',
                    'dean_name'   => 'Prof. Alexis Ndong',
                    'is_active'   => true,
                ],
                'department' => [
                    'name'      => 'Santé Publique et Épidémiologie',
                    'code'      => 'SPE',
                    'head_name' => 'Dr. Sylvie Moussavou',
                    'is_active' => true,
                ],
                'prefix' => 'SPE',
                'courses' => [
                    // L1 - S1 (4 courses, 2 tronc commun)
                    ['code' => 'SPE101', 'name' => 'Introduction à la Santé Publique', 'level' => 'L1', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'SPE102', 'name' => 'Épidémiologie Descriptive',        'level' => 'L1', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'TC301',  'name' => 'Informatique et Bases de Données', 'level' => 'L1', 'sem' => '1', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    ['code' => 'TC302',  'name' => 'Statistiques de Santé',            'level' => 'L1', 'sem' => '1', 'cr' => 4, 'h' => 3, 'type' => 'tronc_commun'],
                    // L1 - S2 (3 courses, 1 tronc commun)
                    ['code' => 'SPE106', 'name' => 'Maladies Transmissibles',          'level' => 'L1', 'sem' => '2', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    ['code' => 'SPE107', 'name' => "Nutrition et Santé",               'level' => 'L1', 'sem' => '2', 'cr' => 4, 'h' => 3, 'type' => 'specialisation'],
                    ['code' => 'TC303',  'name' => 'Anglais Scientifique',             'level' => 'L1', 'sem' => '2', 'cr' => 2, 'h' => 2, 'type' => 'tronc_commun'],
                    // L1 - S3 (2 courses, 1 tronc commun)
                    ['code' => 'SPE110', 'name' => 'Hygiène et Assainissement',        'level' => 'L1', 'sem' => '3', 'cr' => 4, 'h' => 3, 'type' => 'specialisation'],
                    ['code' => 'TC304',  'name' => 'Rédaction Scientifique',           'level' => 'L1', 'sem' => '3', 'cr' => 3, 'h' => 2, 'type' => 'tronc_commun'],
                    // L2 - S1 (1 course)
                    ['code' => 'SPE201', 'name' => 'Épidémiologie Analytique',         'level' => 'L2', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                    // L2 - S2 (1 course, tronc commun)
                    ['code' => 'TC305',  'name' => 'Gestion des Systèmes de Santé',    'level' => 'L2', 'sem' => '2', 'cr' => 4, 'h' => 3, 'type' => 'tronc_commun'],
                    // L3 - S1 (1 course)
                    ['code' => 'SPE301', 'name' => 'Politiques de Santé',              'level' => 'L3', 'sem' => '1', 'cr' => 5, 'h' => 4, 'type' => 'specialisation'],
                ],
            ],
        ];

        $departments = [];
        foreach ($facultyData as $data) {
            $faculty = Faculty::create($data['faculty']);
            $dept = Department::create(array_merge($data['department'], ['faculty_id' => $faculty->id]));
            $departments[$data['prefix']] = $dept;

            foreach ($data['courses'] as $c) {
                Course::create([
                    'department_id'  => $dept->id,
                    'code'           => $c['code'],
                    'name'           => $c['name'],
                    'credits'        => $c['cr'],
                    'level'          => $c['level'],
                    'semester'       => $c['sem'],
                    'hours_per_week' => $c['h'],
                    'course_type'    => $c['type'],
                    'is_active'      => true,
                ]);
            }
            $this->command->info("Faculty {$faculty->name} created with 12 courses.");
        }

        // ── 3. Create 3 test students ──────────────────────────────────────────
        $students = [
            [
                'user' => [
                    'username'   => 'etudiant1',
                    'email'      => 'etudiant1@esl.local',
                    'password'   => Hash::make('password123'),
                    'first_name' => 'Alice',
                    'last_name'  => 'MBOMA',
                    'role'       => 'student',
                    'phone'      => '+241 07 11 22 33',
                    'is_active'  => true,
                ],
                'dept_prefix' => 'MED',
                'level'       => 'L1',
            ],
            [
                'user' => [
                    'username'   => 'etudiant2',
                    'email'      => 'etudiant2@esl.local',
                    'password'   => Hash::make('password123'),
                    'first_name' => 'Bruno',
                    'last_name'  => 'NKOGHE',
                    'role'       => 'student',
                    'phone'      => '+241 07 44 55 66',
                    'is_active'  => true,
                ],
                'dept_prefix' => 'INF',
                'level'       => 'L1',
            ],
            [
                'user' => [
                    'username'   => 'etudiant3',
                    'email'      => 'etudiant3@esl.local',
                    'password'   => Hash::make('password123'),
                    'first_name' => 'Carole',
                    'last_name'  => 'OYONO',
                    'role'       => 'student',
                    'phone'      => '+241 07 77 88 99',
                    'is_active'  => true,
                ],
                'dept_prefix' => 'SPE',
                'level'       => 'L1',
            ],
        ];

        $studentCounter = 1;
        foreach ($students as $s) {
            $user = User::create($s['user']);
            $dept = $departments[$s['dept_prefix']];
            $year = date('Y');
            Student::create([
                'user_id'          => $user->id,
                'department_id'    => $dept->id,
                'student_id'       => 'STU-' . str_pad($studentCounter++, 5, '0', STR_PAD_LEFT),
                'level'            => $s['level'],
                'current_semester' => '1',
                'enrollment_date'  => now(),
                'status'           => 'active',
            ]);
            $this->command->info("Student {$user->first_name} {$user->last_name} created ({$dept->code} / {$s['level']}).");
        }

        $this->command->info('');
        $this->command->info('✅ Test data seeded successfully!');
        $this->command->info('Students: etudiant1@esl.local / etudiant2@esl.local / etudiant3@esl.local (password: password123)');
    }
}
