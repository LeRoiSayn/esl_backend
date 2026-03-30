<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'student_id',
        'level',
        'current_semester',
        'enrollment_date',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'status',
        'retake_courses',
    ];

    protected $casts = [
        'enrollment_date'  => 'date',
        'retake_courses'   => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function fees()
    {
        return $this->hasMany(StudentFee::class);
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'enrollments', 'student_id', 'class_id')
            ->withPivot('enrollment_date', 'status')
            ->withTimestamps();
    }

    public function getTotalFeesAttribute(): float
    {
        return $this->fees->sum('amount');
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->fees->sum('paid_amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_fees - $this->total_paid;
    }

    public function getAttendanceRateAttribute(): float
    {
        $enrollmentIds = $this->enrollments->pluck('id');
        $totalAttendance = Attendance::whereIn('enrollment_id', $enrollmentIds)->count();
        $presentCount = Attendance::whereIn('enrollment_id', $enrollmentIds)
            ->whereIn('status', ['present', 'late'])
            ->count();
        
        return $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100, 2) : 0;
    }
}
