<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'course_id',
        'teacher_id',
        'section',
        'room',
        'capacity',
        'academic_year',
        'semester',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'class_id', 'student_id')
            ->withPivot('enrollment_date', 'status')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    public function getEnrolledCountAttribute(): int
    {
        return $this->enrollments()->where('status', 'enrolled')->count();
    }

    public function getAvailableSpotsAttribute(): int
    {
        return $this->capacity - $this->enrolled_count;
    }
}
