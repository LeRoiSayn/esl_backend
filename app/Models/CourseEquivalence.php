<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseEquivalence extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'original_course_name',
        'original_institution',
        'equivalent_course_id',
        'original_grade',
        'original_credits',
        'status',
        'reviewed_by',
        'notes',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function equivalentCourse()
    {
        return $this->belongsTo(Course::class, 'equivalent_course_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
