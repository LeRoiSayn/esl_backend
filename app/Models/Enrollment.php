<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_id',
        'enrollment_date',
        'status',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function latestGrade()
    {
        return $this->hasOne(Grade::class)->latest();
    }

    public function course()
    {
        return $this->hasOneThrough(
            \App\Models\Course::class,
            ClassModel::class,
            'id',         // foreign key on class_models matching enrollments.class_id
            'id',         // foreign key on courses matching class_models.course_id
            'class_id',   // local key on enrollments
            'course_id'   // local key on class_models
        );
    }
}
