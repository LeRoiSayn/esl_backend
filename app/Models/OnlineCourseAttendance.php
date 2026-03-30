<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlineCourseAttendance extends Model
{
    use HasFactory;

    protected $table = 'online_course_attendance';

    protected $fillable = [
        'online_course_id',
        'student_id',
        'joined_at',
        'left_at',
        'duration_minutes',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function onlineCourse()
    {
        return $this->belongsTo(OnlineCourse::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
