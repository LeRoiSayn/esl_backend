<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'course_id',
        'teacher_id',
        'title',
        'description',
        'instructions',
        'total_points',
        'due_date',
        'allow_late_submission',
        'late_penalty_percent',
        'allow_multiple_submissions',
        'allowed_file_types',
        'max_file_size_mb',
        'status',
    ];

    // Naive datetime format — no timezone suffix — so the frontend displays
    // wall-clock time without UTC offset conversion.
    protected $casts = [
        'due_date' => 'datetime:Y-m-d\TH:i:s',
        'allow_late_submission' => 'boolean',
        'allow_multiple_submissions' => 'boolean',
        'allowed_file_types' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isOverdue()
    {
        return now() > $this->due_date;
    }

    public function canSubmit()
    {
        if ($this->status !== 'published') {
            return false;
        }
        if (!$this->isOverdue()) {
            return true;
        }
        return $this->allow_late_submission;
    }
}
