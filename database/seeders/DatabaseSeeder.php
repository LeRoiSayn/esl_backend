<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Staff users (admin, registrar, finance) ─────────────────────────
        $staff = [
            [
                'username'   => 'diallo1',
                'email'      => 'diallogloire@gmail.com',
                'first_name' => 'Gloire',
                'last_name'  => 'Diallo',
                'role'       => 'admin',
                'phone'      => '+241 01 00 00 01',
            ],
            [
                'username'   => 'ujdps1',
                'email'      => 'ujdps2024@gmail.com',
                'first_name' => 'UJDPS',
                'last_name'  => 'Admin',
                'role'       => 'admin',
                'phone'      => '+241 01 00 00 02',
            ],
            [
                'username'   => 'akounga1',
                'email'      => 'diallogloire+registrar@gmail.com',
                'first_name' => 'Alex',
                'last_name'  => 'Akounga',
                'role'       => 'registrar',
                'phone'      => '+241 01 23 45 69',
            ],
            [
                'username'   => 'mboumba1',
                'email'      => 'ujdps2024+finance@gmail.com',
                'first_name' => 'Pacôme',
                'last_name'  => 'Mboumba',
                'role'       => 'finance',
                'phone'      => '+241 01 00 00 04',
            ],
        ];

        foreach ($staff as $data) {
            User::firstOrCreate(['username' => $data['username']], array_merge($data, [
                'password'  => Hash::make('M00dle!!'),
                'is_active' => true,
            ]));
        }

        // ── 2. Faculties & Departments ─────────────────────────────────────────
        $faculties = [
            [
                'faculty' => [
                    'name'      => 'Faculté de Médecine',
                    'code'      => 'FMED',
                    'dean_name' => 'Prof. Jean-Baptiste Mboumba',
                    'is_active' => true,
                ],
                'departments' => [
                    ['name' => 'Sciences Médicales',       'code' => 'MED', 'head_name' => 'Dr. Henriette Nzé'],
                    ['name' => 'Médecine Communautaire',   'code' => 'MCO', 'head_name' => 'Dr. Serge Ondo'],
                ],
            ],
            [
                'faculty' => [
                    'name'      => 'Faculté des Sciences Infirmières',
                    'code'      => 'FSIN',
                    'dean_name' => 'Prof. Marie-Claire Obame',
                    'is_active' => true,
                ],
                'departments' => [
                    ['name' => 'Soins Infirmiers',         'code' => 'INF', 'head_name' => 'Dr. Pascal Bivigou'],
                    ['name' => 'Obstétrique',              'code' => 'OBS', 'head_name' => 'Dr. Carine Minko'],
                ],
            ],
            [
                'faculty' => [
                    'name'      => 'Faculté de Pharmacie',
                    'code'      => 'FPHA',
                    'dean_name' => 'Prof. Robert Nguema',
                    'is_active' => true,
                ],
                'departments' => [
                    ['name' => 'Sciences Pharmaceutiques', 'code' => 'PHA', 'head_name' => 'Dr. Edwige Moussavou'],
                ],
            ],
        ];

        $deptMap = [];
        foreach ($faculties as $fData) {
            $faculty = Faculty::firstOrCreate(['code' => $fData['faculty']['code']], $fData['faculty']);
            foreach ($fData['departments'] as $dData) {
                $dept = Department::firstOrCreate(
                    ['code' => $dData['code']],
                    array_merge($dData, ['faculty_id' => $faculty->id, 'is_active' => true])
                );
                $deptMap[$dData['code']] = $dept->id;
            }
        }

        // ── 3. Courses L1/L2/L3 par département ────────────────────────────────
        $coursesByDept = [
            'MED' => [
                // L1
                ['code'=>'MED101','name'=>'Anatomie Générale I',            'level'=>'L1','semester'=>'1','credits'=>6,'hours_per_week'=>4],
                ['code'=>'MED102','name'=>'Biologie Cellulaire',            'level'=>'L1','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED103','name'=>'Chimie Générale et Organique',   'level'=>'L1','semester'=>'1','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED104','name'=>'Biophysique Médicale',           'level'=>'L1','semester'=>'1','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED105','name'=>'Introduction aux Sciences Médicales','level'=>'L1','semester'=>'2','credits'=>3,'hours_per_week'=>2],
                ['code'=>'MED106','name'=>'Anatomie Générale II',           'level'=>'L1','semester'=>'2','credits'=>6,'hours_per_week'=>4],
                ['code'=>'MED107','name'=>'Histologie et Embryologie',      'level'=>'L1','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED108','name'=>'Biochimie Structurale',          'level'=>'L1','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED109','name'=>'Biostatistique Médicale',        'level'=>'L1','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED110','name'=>'Physiologie Générale',           'level'=>'L1','semester'=>'3','credits'=>5,'hours_per_week'=>4],
                // L2
                ['code'=>'MED201','name'=>'Anatomie Pathologique I',        'level'=>'L2','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED202','name'=>'Microbiologie Médicale',         'level'=>'L2','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED203','name'=>'Physiologie des Grandes Fonctions','level'=>'L2','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED204','name'=>'Biochimie Métabolique',          'level'=>'L2','semester'=>'1','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED205','name'=>'Immunologie Fondamentale',       'level'=>'L2','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED206','name'=>'Pharmacologie Générale',         'level'=>'L2','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED207','name'=>'Sémiologie Médicale',            'level'=>'L2','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED208','name'=>'Parasitologie et Mycologie',     'level'=>'L2','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED209','name'=>'Hématologie Fondamentale',       'level'=>'L2','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                ['code'=>'MED210','name'=>'Santé Publique et Épidémiologie','level'=>'L2','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                // L3
                ['code'=>'MED301','name'=>'Médecine Interne I',             'level'=>'L3','semester'=>'1','credits'=>6,'hours_per_week'=>5],
                ['code'=>'MED302','name'=>'Chirurgie Générale',             'level'=>'L3','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED303','name'=>'Pédiatrie',                      'level'=>'L3','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED304','name'=>'Gynécologie-Obstétrique',        'level'=>'L3','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED305','name'=>'Neurologie',                     'level'=>'L3','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED306','name'=>'Médecine Interne II',            'level'=>'L3','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED307','name'=>'Urgences Médicales',             'level'=>'L3','semester'=>'3','credits'=>5,'hours_per_week'=>4],
                ['code'=>'MED308','name'=>'Stage Clinique Intégré',         'level'=>'L3','semester'=>'3','credits'=>6,'hours_per_week'=>5],
            ],
            'INF' => [
                // L1
                ['code'=>'INF101','name'=>'Fondements des Soins Infirmiers', 'level'=>'L1','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF102','name'=>'Anatomie et Physiologie I',       'level'=>'L1','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF103','name'=>'Microbiologie et Hygiène',        'level'=>'L1','semester'=>'1','credits'=>4,'hours_per_week'=>3],
                ['code'=>'INF104','name'=>'Psychologie des Soins',           'level'=>'L1','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'INF105','name'=>'Anatomie et Physiologie II',      'level'=>'L1','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF106','name'=>'Pharmacologie Infirmière I',      'level'=>'L1','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'INF107','name'=>'Communication Thérapeutique',     'level'=>'L1','semester'=>'3','credits'=>3,'hours_per_week'=>2],
                ['code'=>'INF108','name'=>'Éthique Infirmière',              'level'=>'L1','semester'=>'3','credits'=>3,'hours_per_week'=>2],
                // L2
                ['code'=>'INF201','name'=>'Soins Infirmiers Médicaux',       'level'=>'L2','semester'=>'1','credits'=>6,'hours_per_week'=>5],
                ['code'=>'INF202','name'=>'Soins Infirmiers Chirurgicaux',   'level'=>'L2','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF203','name'=>'Pharmacologie Infirmière II',     'level'=>'L2','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'INF204','name'=>'Soins Pédiatriques',              'level'=>'L2','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF205','name'=>'Soins en Santé Mentale',          'level'=>'L2','semester'=>'3','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF206','name'=>'Soins Communautaires',            'level'=>'L2','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                // L3
                ['code'=>'INF301','name'=>'Soins Intensifs et Réanimation',  'level'=>'L3','semester'=>'1','credits'=>6,'hours_per_week'=>5],
                ['code'=>'INF302','name'=>'Soins Obstétriques',              'level'=>'L3','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF303','name'=>'Management Infirmier',            'level'=>'L3','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'INF304','name'=>'Recherche en Sciences Infirmières','level'=>'L3','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'INF305','name'=>'Stage Clinique de Fin d\'Études', 'level'=>'L3','semester'=>'3','credits'=>8,'hours_per_week'=>6],
            ],
            'PHA' => [
                // L1
                ['code'=>'PHA101','name'=>'Chimie Pharmaceutique I',         'level'=>'L1','semester'=>'1','credits'=>6,'hours_per_week'=>4],
                ['code'=>'PHA102','name'=>'Botanique Médicinale',            'level'=>'L1','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA103','name'=>'Biologie Cellulaire et Moléc.',   'level'=>'L1','semester'=>'1','credits'=>4,'hours_per_week'=>3],
                ['code'=>'PHA104','name'=>'Mathématiques pour Pharmaciens',  'level'=>'L1','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'PHA105','name'=>'Chimie Pharmaceutique II',        'level'=>'L1','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA106','name'=>'Anatomie et Physiologie',         'level'=>'L1','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA107','name'=>'Biochimie Pharmaceutique',        'level'=>'L1','semester'=>'3','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA108','name'=>'Terminologie Médicale',           'level'=>'L1','semester'=>'3','credits'=>3,'hours_per_week'=>2],
                // L2
                ['code'=>'PHA201','name'=>'Pharmacognosie I',                'level'=>'L2','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA202','name'=>'Pharmacologie Générale',          'level'=>'L2','semester'=>'1','credits'=>6,'hours_per_week'=>5],
                ['code'=>'PHA203','name'=>'Galénique I',                     'level'=>'L2','semester'=>'2','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA204','name'=>'Microbiologie Pharmaceutique',    'level'=>'L2','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'PHA205','name'=>'Pharmacognosie II',               'level'=>'L2','semester'=>'3','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA206','name'=>'Toxicologie Générale',            'level'=>'L2','semester'=>'3','credits'=>4,'hours_per_week'=>3],
                // L3
                ['code'=>'PHA301','name'=>'Pharmacologie Clinique',          'level'=>'L3','semester'=>'1','credits'=>6,'hours_per_week'=>5],
                ['code'=>'PHA302','name'=>'Galénique II et Biopharmaceutique','level'=>'L3','semester'=>'1','credits'=>5,'hours_per_week'=>4],
                ['code'=>'PHA303','name'=>'Pharmacie Clinique et Hospitalière','level'=>'L3','semester'=>'2','credits'=>6,'hours_per_week'=>5],
                ['code'=>'PHA304','name'=>'Réglementation Pharmaceutique',   'level'=>'L3','semester'=>'2','credits'=>4,'hours_per_week'=>3],
                ['code'=>'PHA305','name'=>'Stage en Officine',               'level'=>'L3','semester'=>'3','credits'=>8,'hours_per_week'=>6],
            ],
        ];

        foreach ($coursesByDept as $deptCode => $courses) {
            if (!isset($deptMap[$deptCode])) continue;
            $deptId = $deptMap[$deptCode];
            foreach ($courses as $c) {
                Course::firstOrCreate(['code' => $c['code']], array_merge($c, [
                    'department_id'  => $deptId,
                    'description'    => $c['name'],
                    'course_type'    => 'tronc_commun',
                    'is_active'      => true,
                ]));
            }
        }

        // ── 4. Teacher users (6) avec noms gabonais ────────────────────────────
        $teachers = [
            ['first'=>'Brice',     'last'=>'Nze',         'dept'=>'MED', 'spec'=>'Anatomie et Physiologie',         'qual'=>'Docteur en Médecine',          'email'=>'diallogloire+teacher1@gmail.com'],
            ['first'=>'Ornella',   'last'=>'Moussavou',   'dept'=>'MED', 'spec'=>'Pharmacologie',                   'qual'=>'Docteur en Médecine',          'email'=>'ujdps2024+teacher2@gmail.com'],
            ['first'=>'Gildas',    'last'=>'Boundolo',    'dept'=>'INF', 'spec'=>'Soins Infirmiers Chirurgicaux',   'qual'=>'Master en Sciences Infirmières','email'=>'diallogloire+teacher3@gmail.com'],
            ['first'=>'Edwige',    'last'=>'Bekale',      'dept'=>'INF', 'spec'=>'Pédiatrie et Soins Maternels',    'qual'=>'Master en Sciences Infirmières','email'=>'ujdps2024+teacher4@gmail.com'],
            ['first'=>'Franck',    'last'=>'Essone',      'dept'=>'PHA', 'spec'=>'Chimie Pharmaceutique',           'qual'=>'Docteur en Pharmacie',         'email'=>'diallogloire+teacher5@gmail.com'],
            ['first'=>'Mireille',  'last'=>'Bivigou',     'dept'=>'PHA', 'spec'=>'Pharmacognosie et Galénique',    'qual'=>'Docteur en Pharmacie',         'email'=>'ujdps2024+teacher6@gmail.com'],
        ];

        foreach ($teachers as $i => $t) {
            $username = strtolower($t['first']) . '1';
            $user = User::firstOrCreate(['username' => $username], [
                'email'      => $t['email'],
                'password'   => Hash::make('M00dle!!'),
                'first_name' => $t['first'],
                'last_name'  => $t['last'],
                'role'       => 'teacher',
                'phone'      => '+241 06 ' . str_pad($i + 10, 2, '0', STR_PAD_LEFT) . ' 00 00',
                'is_active'  => true,
            ]);

            if (!Teacher::where('user_id', $user->id)->exists()) {
                Teacher::create([
                    'user_id'        => $user->id,
                    'department_id'  => $deptMap[$t['dept']] ?? array_values($deptMap)[0],
                    'employee_id'    => 'TCH-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'qualification'  => $t['qual'],
                    'specialization' => $t['spec'],
                    'hire_date'      => '2022-09-01',
                    'status'         => 'active',
                ]);
            }
        }

        // ── 5. Student users (10) avec noms gabonais ───────────────────────────
        $students = [
            ['first'=>'Merveille',  'last'=>'Ngoua',     'level'=>'L1', 'dept'=>'MED', 'sem'=>'1', 'email'=>'diallogloire+student1@gmail.com'],
            ['first'=>'Darrel',     'last'=>'Mba',       'level'=>'L1', 'dept'=>'MED', 'sem'=>'1', 'email'=>'ujdps2024+student2@gmail.com'],
            ['first'=>'Kevine',     'last'=>'Nguema',    'level'=>'L1', 'dept'=>'INF', 'sem'=>'1', 'email'=>'diallogloire+student3@gmail.com'],
            ['first'=>'Chancelle',  'last'=>'Ondo',      'level'=>'L1', 'dept'=>'INF', 'sem'=>'2', 'email'=>'ujdps2024+student4@gmail.com'],
            ['first'=>'Exaucé',     'last'=>'Minko',     'level'=>'L2', 'dept'=>'MED', 'sem'=>'1', 'email'=>'diallogloire+student5@gmail.com'],
            ['first'=>'Danielle',   'last'=>'Itoua',     'level'=>'L2', 'dept'=>'MED', 'sem'=>'2', 'email'=>'ujdps2024+student6@gmail.com'],
            ['first'=>'Joris',      'last'=>'Moundounga','level'=>'L2', 'dept'=>'INF', 'sem'=>'1', 'email'=>'diallogloire+student7@gmail.com'],
            ['first'=>'Grâce',      'last'=>'Obame',     'level'=>'L3', 'dept'=>'MED', 'sem'=>'1', 'email'=>'ujdps2024+student8@gmail.com'],
            ['first'=>'Christophe', 'last'=>'Nze',       'level'=>'L3', 'dept'=>'PHA', 'sem'=>'2', 'email'=>'diallogloire+student9@gmail.com'],
            ['first'=>'Melissa',    'last'=>'Bekale',    'level'=>'L3', 'dept'=>'PHA', 'sem'=>'1', 'email'=>'ujdps2024+student10@gmail.com'],
        ];

        foreach ($students as $i => $s) {
            $username = strtolower(preg_replace('/[^a-zA-Z]/', '', $s['first'])) . '1';
            $baseUsername = $username;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . (++$counter);
            }

            $user = User::create([
                'username'   => $username,
                'email'      => $s['email'],
                'password'   => Hash::make('M00dle!!'),
                'first_name' => $s['first'],
                'last_name'  => $s['last'],
                'role'       => 'student',
                'phone'      => '+241 07 ' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . ' 00 00',
                'is_active'  => true,
            ]);

            Student::create([
                'user_id'          => $user->id,
                'department_id'    => $deptMap[$s['dept']] ?? array_values($deptMap)[0],
                'student_id'       => 'ESL-' . date('Y') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'level'            => $s['level'],
                'current_semester' => $s['sem'],
                'enrollment_date'  => '2024-09-01',
                'status'           => 'active',
            ]);
        }

        // ── 6. System settings & academic levels ──────────────────────────────
        $this->call(SystemConfigSeeder::class);

        // ── 7. Classes, enrollments, grades & finance ──────────────────────────
        $this->call(FullTestDataSeeder::class);
    }
}
