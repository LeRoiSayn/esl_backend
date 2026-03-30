<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teacher_id',
        'title',
        'description',
        'duration_minutes',
        'total_points',
        'passing_score',
        'max_attempts',
        'shuffle_questions',
        'show_answers_after',
        'proctoring_enabled',
        'available_from',
        'available_until',
        'status',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'show_answers_after' => 'boolean',
        'proctoring_enabled' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isAvailable()
    {
        $now = now();
        return $this->status === 'published' 
            && ($this->available_from === null || $now >= $this->available_from)
            && ($this->available_until === null || $now <= $this->available_until);
    }
}
