<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'description',
        'credits',
        'level',
        'semester',
        'course_type',
        'hours_per_week',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credits' => 'integer',
        'hours_per_week' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    public function getEnrollmentCountAttribute(): int
    {
        return Enrollment::whereIn('class_id', $this->classes()->pluck('id'))->count();
    }
}
