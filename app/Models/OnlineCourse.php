<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlineCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'class_id',
        'teacher_id',
        'title',
        'description',
        'type',
        'meeting_url',
        'meeting_id',
        'recording_url',
        'scheduled_at',
        'duration_minutes',
        'status',
    ];

    // Use naive datetime format (no timezone suffix) so the frontend displays
    // wall-clock time without any UTC offset conversion.
    protected $casts = [
        'scheduled_at' => 'datetime:Y-m-d\TH:i:s',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attendance()
    {
        return $this->hasMany(OnlineCourseAttendance::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'online_course_attendance')
            ->withPivot(['joined_at', 'left_at', 'duration_minutes'])
            ->withTimestamps();
    }
}
