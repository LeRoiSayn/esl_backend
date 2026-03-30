<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_id',
        'qualification',
        'specialization',
        'hire_date',
        'salary',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary' => 'decimal:2',
    ];

    protected $hidden = [
        'salary',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    public function getStudentCountAttribute(): int
    {
        return Enrollment::whereIn('class_id', $this->classes()->pluck('id'))
            ->distinct('student_id')
            ->count('student_id');
    }
}
