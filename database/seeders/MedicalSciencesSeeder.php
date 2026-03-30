<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;

class MedicalSciencesSeeder extends Seeder
{
    public function run(): void
    {
        // Avoid duplicate if already seeded
        if (Faculty::where('code', 'FMED')->exists()) {
            $this->command->warn('Medical Sciences Faculty already exists. Skipping.');
            return;
        }

        $faculty = Faculty::create([
            'name' => 'Faculté des Sciences Médicales',
            'code' => 'FMED',
            'description' => 'Faculté des Sciences Médicales - Formation en médecine générale (3 ans, 9 semestres, 39 cours)',
            'dean_name' => 'Prof. Jean-Baptiste Mboumba',
            'is_active' => true,
        ]);

        $dept = Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Sciences Médicales de Base',
            'code' => 'SMB',
            'description' => 'Département des Sciences Médicales de Base - Cursus pré-clinique et clinique (3 ans × 3 semestres)',
            'head_name' => 'Dr. Henriette Nzé',
            'is_active' => true,
        ]);

        // 39 courses: 3 years × 3 semesters ≈ 4-5 courses/semester
        $courses = [
            // ===== L1 - Semestre 1 (5 cours) =====
            ['code' => 'MED101', 'name' => 'Anatomie Générale I',             'credits' => 6, 'level' => 'L1', 'semester' => '1', 'h' => 4],
            ['code' => 'MED102', 'name' => 'Biologie Cellulaire',             'credits' => 5, 'level' => 'L1', 'semester' => '1', 'h' => 4],
            ['code' => 'MED103', 'name' => 'Chimie Générale et Organique',    'credits' => 4, 'level' => 'L1', 'semester' => '1', 'h' => 3],
            ['code' => 'MED104', 'name' => 'Biophysique Médicale',            'credits' => 4, 'level' => 'L1', 'semester' => '1', 'h' => 3],
            ['code' => 'MED105', 'name' => 'Introduction aux Sciences Médicales', 'credits' => 3, 'level' => 'L1', 'semester' => '1', 'h' => 2],

            // ===== L1 - Semestre 2 (4 cours) =====
            ['code' => 'MED106', 'name' => 'Anatomie Générale II',            'credits' => 6, 'level' => 'L1', 'semester' => '2', 'h' => 4],
            ['code' => 'MED107', 'name' => 'Histologie et Embryologie',       'credits' => 5, 'level' => 'L1', 'semester' => '2', 'h' => 4],
            ['code' => 'MED108', 'name' => 'Biochimie Structurale',           'credits' => 5, 'level' => 'L1', 'semester' => '2', 'h' => 4],
            ['code' => 'MED109', 'name' => 'Biostatistique et Informatique Médicale', 'credits' => 4, 'level' => 'L1', 'semester' => '2', 'h' => 3],

            // ===== L1 - Semestre 3 (4 cours) =====
            ['code' => 'MED110', 'name' => 'Physiologie Générale',            'credits' => 5, 'level' => 'L1', 'semester' => '3', 'h' => 4],
            ['code' => 'MED111', 'name' => 'Génétique Médicale',              'credits' => 4, 'level' => 'L1', 'semester' => '3', 'h' => 3],
            ['code' => 'MED112', 'name' => 'Anglais Médical',                 'credits' => 3, 'level' => 'L1', 'semester' => '3', 'h' => 2],
            ['code' => 'MED113', 'name' => 'Méthodologie de la Recherche',    'credits' => 3, 'level' => 'L1', 'semester' => '3', 'h' => 2],

            // ===== L2 - Semestre 1 (5 cours) =====
            ['code' => 'MED201', 'name' => 'Anatomie Pathologique I',         'credits' => 5, 'level' => 'L2', 'semester' => '1', 'h' => 4],
            ['code' => 'MED202', 'name' => 'Microbiologie Médicale',          'credits' => 5, 'level' => 'L2', 'semester' => '1', 'h' => 4],
            ['code' => 'MED203', 'name' => 'Physiologie des Grandes Fonctions', 'credits' => 5, 'level' => 'L2', 'semester' => '1', 'h' => 4],
            ['code' => 'MED204', 'name' => 'Biochimie Métabolique',           'credits' => 4, 'level' => 'L2', 'semester' => '1', 'h' => 3],
            ['code' => 'MED205', 'name' => 'Immunologie Fondamentale',        'credits' => 4, 'level' => 'L2', 'semester' => '1', 'h' => 3],

            // ===== L2 - Semestre 2 (4 cours) =====
            ['code' => 'MED206', 'name' => 'Pharmacologie Générale',          'credits' => 5, 'level' => 'L2', 'semester' => '2', 'h' => 4],
            ['code' => 'MED207', 'name' => 'Sémiologie Médicale',             'credits' => 5, 'level' => 'L2', 'semester' => '2', 'h' => 4],
            ['code' => 'MED208', 'name' => 'Parasitologie et Mycologie',      'credits' => 4, 'level' => 'L2', 'semester' => '2', 'h' => 3],
            ['code' => 'MED209', 'name' => 'Hématologie Fondamentale',        'credits' => 4, 'level' => 'L2', 'semester' => '2', 'h' => 3],

            // ===== L2 - Semestre 3 (4 cours) =====
            ['code' => 'MED210', 'name' => 'Anatomie Pathologique II',        'credits' => 5, 'level' => 'L2', 'semester' => '3', 'h' => 4],
            ['code' => 'MED211', 'name' => 'Sémiologie Chirurgicale',         'credits' => 5, 'level' => 'L2', 'semester' => '3', 'h' => 4],
            ['code' => 'MED212', 'name' => 'Radiologie et Imagerie Médicale', 'credits' => 4, 'level' => 'L2', 'semester' => '3', 'h' => 3],
            ['code' => 'MED213', 'name' => 'Santé Publique et Épidémiologie', 'credits' => 4, 'level' => 'L2', 'semester' => '3', 'h' => 3],

            // ===== L3 - Semestre 1 (5 cours) =====
            ['code' => 'MED301', 'name' => 'Médecine Interne I',              'credits' => 6, 'level' => 'L3', 'semester' => '1', 'h' => 5],
            ['code' => 'MED302', 'name' => 'Chirurgie Générale',              'credits' => 5, 'level' => 'L3', 'semester' => '1', 'h' => 4],
            ['code' => 'MED303', 'name' => 'Pédiatrie',                       'credits' => 5, 'level' => 'L3', 'semester' => '1', 'h' => 4],
            ['code' => 'MED304', 'name' => 'Gynécologie-Obstétrique',         'credits' => 5, 'level' => 'L3', 'semester' => '1', 'h' => 4],
            ['code' => 'MED305', 'name' => 'Pharmacologie Clinique',          'credits' => 4, 'level' => 'L3', 'semester' => '1', 'h' => 3],

            // ===== L3 - Semestre 2 (4 cours) =====
            ['code' => 'MED306', 'name' => 'Médecine Interne II',             'credits' => 6, 'level' => 'L3', 'semester' => '2', 'h' => 5],
            ['code' => 'MED307', 'name' => 'Maladies Infectieuses et Tropicales', 'credits' => 5, 'level' => 'L3', 'semester' => '2', 'h' => 4],
            ['code' => 'MED308', 'name' => 'Neurologie',                      'credits' => 4, 'level' => 'L3', 'semester' => '2', 'h' => 3],
            ['code' => 'MED309', 'name' => 'Psychiatrie',                     'credits' => 4, 'level' => 'L3', 'semester' => '2', 'h' => 3],

            // ===== L3 - Semestre 3 (4 cours) =====
            ['code' => 'MED310', 'name' => "Médecine d'Urgence",              'credits' => 5, 'level' => 'L3', 'semester' => '3', 'h' => 4],
            ['code' => 'MED311', 'name' => 'Éthique et Déontologie Médicale', 'credits' => 3, 'level' => 'L3', 'semester' => '3', 'h' => 2],
            ['code' => 'MED312', 'name' => 'Stage Clinique Hospitalier',      'credits' => 6, 'level' => 'L3', 'semester' => '3', 'h' => 5],
            ['code' => 'MED399', 'name' => 'Mémoire de Fin de Cycle',         'credits' => 8, 'level' => 'L3', 'semester' => '3', 'h' => 4],
        ];

        foreach ($courses as $course) {
            Course::create([
                'department_id' => $dept->id,
                'code'          => $course['code'],
                'name'          => $course['name'],
                'credits'       => $course['credits'],
                'level'         => $course['level'],
                'semester'      => $course['semester'],
                'hours_per_week' => $course['h'],
                'is_active'     => true,
            ]);
        }

        $this->command->info('Medical Sciences Faculty created with 39 courses across 3 years × 3 semesters.');
    }
}
