<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'attendance_score',
        'quiz_score',
        'continuous_assessment',
        'exam_score',
        'final_grade',
        'letter_grade',
        'remarks',
        'graded_by',
        'graded_at',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'attendance_score'     => 'decimal:2',
        'quiz_score'           => 'decimal:2',
        'continuous_assessment' => 'decimal:2',
        'exam_score'           => 'decimal:2',
        'final_grade'          => 'decimal:2',
        'graded_at'            => 'datetime',
        'validated_at'         => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function gradedBy()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public static function calculateLetterGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D+';
        if ($score >= 40) return 'D';
        return 'F';
    }

    /**
     * Calculate final grade from 4 components:
     *   Attendance  max 10 pts
     *   Quiz        max 20 pts
     *   CA (CC)     max 30 pts
     *   Final exam  max 40 pts
     *   Total max = 100
     */
    public static function calculateFinalGrade(
        float $ca,
        float $exam,
        float $quiz = 0,
        float $attendance = 0
    ): float {
        return round($attendance + $quiz + $ca + $exam, 2);
    }

    public function isPassing(): bool
    {
        return $this->final_grade >= 50;
    }
}
