<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;
use App\Models\AcademicLevel;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        // ==================== SYSTEM SETTINGS ====================
        $settings = [
            // Institution
            ['key' => 'institution_name',    'value' => 'École de Santé de Libreville', 'type' => 'string',  'group' => 'institution', 'label' => 'Nom de l\'institution'],
            ['key' => 'institution_logo',    'value' => 'esl-logo.png',                  'type' => 'string',  'group' => 'institution', 'label' => 'Fichier logo (dans /public)'],
            ['key' => 'institution_address', 'value' => '',                               'type' => 'string',  'group' => 'institution', 'label' => 'Adresse'],
            ['key' => 'institution_phone',   'value' => '',                               'type' => 'string',  'group' => 'institution', 'label' => 'Téléphone'],
            ['key' => 'institution_email',   'value' => '',                               'type' => 'string',  'group' => 'institution', 'label' => 'Email officiel'],
            ['key' => 'currency',            'value' => 'XAF',                            'type' => 'string',  'group' => 'institution', 'label' => 'Code devise'],
            ['key' => 'currency_symbol',     'value' => 'FCFA',                           'type' => 'string',  'group' => 'institution', 'label' => 'Symbole devise'],
            ['key' => 'timezone',            'value' => 'Africa/Libreville',              'type' => 'string',  'group' => 'institution', 'label' => 'Fuseau horaire (ex: Africa/Libreville, Europe/Paris, UTC)'],
            // Academic
            ['key' => 'current_academic_year', 'value' => '2025-2026', 'type' => 'string', 'group' => 'academic', 'label' => 'Année académique courante'],
            // Grading scale
            ['key' => 'grading_max_score',      'value' => '20',  'type' => 'integer', 'group' => 'grading', 'label' => 'Note maximale'],
            ['key' => 'grading_passing_score',  'value' => '10',  'type' => 'integer', 'group' => 'grading', 'label' => 'Note de passage'],
            ['key' => 'cc_weight',              'value' => '40',  'type' => 'integer', 'group' => 'grading', 'label' => 'Poids Contrôle Continu (%)'],
            ['key' => 'exam_weight',            'value' => '60',  'type' => 'integer', 'group' => 'grading', 'label' => 'Poids Examen Final (%)'],
            // Grade component max points (must sum to 100)
            ['key' => 'grade_weight_attendance', 'value' => '10', 'type' => 'integer', 'group' => 'grading', 'label' => 'Max points - Présence'],
            ['key' => 'grade_weight_quiz',       'value' => '20', 'type' => 'integer', 'group' => 'grading', 'label' => 'Max points - Quiz'],
            ['key' => 'grade_weight_ca',         'value' => '30', 'type' => 'integer', 'group' => 'grading', 'label' => 'Max points - Contrôle Continu'],
            ['key' => 'grade_weight_exam',       'value' => '40', 'type' => 'integer', 'group' => 'grading', 'label' => 'Max points - Examen Final'],
            // Letter grade thresholds (JSON, sorted highest first)
            ['key' => 'grade_letter_thresholds', 'value' => json_encode([
                ['grade' => 'A+', 'min' => 90],
                ['grade' => 'A',  'min' => 85],
                ['grade' => 'A-', 'min' => 80],
                ['grade' => 'B+', 'min' => 75],
                ['grade' => 'B',  'min' => 70],
                ['grade' => 'B-', 'min' => 65],
                ['grade' => 'C+', 'min' => 60],
                ['grade' => 'C',  'min' => 55],
                ['grade' => 'C-', 'min' => 50],
                ['grade' => 'D+', 'min' => 45],
                ['grade' => 'D',  'min' => 40],
            ]), 'type' => 'json', 'group' => 'grading', 'label' => 'Seuils des lettres de grade'],
            // Fee categories
            ['key' => 'fee_categories', 'value' => json_encode(['tuition', 'registration', 'library', 'lab', 'other']), 'type' => 'json', 'group' => 'institution', 'label' => 'Catégories de frais'],
        ];

        foreach ($settings as $s) {
            SystemSetting::updateOrCreate(['key' => $s['key']], $s);
        }

        // ==================== ACADEMIC LEVELS ====================
        $levels = [
            ['code' => 'L1', 'label' => 'Licence 1', 'order' => 1],
            ['code' => 'L2', 'label' => 'Licence 2', 'order' => 2],
            ['code' => 'L3', 'label' => 'Licence 3', 'order' => 3],
            ['code' => 'M1', 'label' => 'Master 1',  'order' => 4],
            ['code' => 'M2', 'label' => 'Master 2',  'order' => 5],
            ['code' => 'D1', 'label' => 'Doctorat 1', 'order' => 6],
            ['code' => 'D2', 'label' => 'Doctorat 2', 'order' => 7],
            ['code' => 'D3', 'label' => 'Doctorat 3', 'order' => 8],
        ];

        foreach ($levels as $l) {
            AcademicLevel::updateOrCreate(['code' => $l['code']], array_merge($l, ['is_active' => true]));
        }
    }
}
