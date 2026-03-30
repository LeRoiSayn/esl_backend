<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'dean_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function getStudentCountAttribute(): int
    {
        return Student::whereIn('department_id', $this->departments()->pluck('id'))->count();
    }

    public function getTeacherCountAttribute(): int
    {
        return Teacher::whereIn('department_id', $this->departments()->pluck('id'))->count();
    }
}
