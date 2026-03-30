<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'file_path',
        'file_name',
        'file_size',
        'is_late',
        'grade',
        'feedback',
        'annotations',
        'graded_by',
        'graded_at',
        'status',
    ];

    protected $casts = [
        'is_late' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function calculateFinalGrade()
    {
        if ($this->grade === null) {
            return null;
        }

        $finalGrade = $this->grade;
        
        if ($this->is_late) {
            $penalty = $this->assignment->late_penalty_percent / 100;
            $finalGrade = $finalGrade * (1 - $penalty);
        }

        return round($finalGrade, 2);
    }
}
