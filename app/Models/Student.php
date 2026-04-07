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
        // Single query via JOIN on student_id — replaces the previous 3-query pattern
        $result = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                COUNT(*)                                                   AS total,
                COUNT(*) FILTER (WHERE a.status IN ('present', 'late'))    AS present_count
            FROM attendance a
            INNER JOIN enrollments e ON e.id = a.enrollment_id
            WHERE e.student_id = ?
        ", [$this->id]);

        return ($result && $result->total > 0)
            ? round(($result->present_count / $result->total) * 100, 2)
            : 0.0;
    }
}
