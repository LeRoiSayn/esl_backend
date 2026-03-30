<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'class_id',
        'teacher_id',
        'title',
        'description',
        'type',
        'file_path',
        'external_url',
        'file_name',
        'file_size',
        'downloadable',
        'download_count',
        'view_count',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function incrementDownloads()
    {
        $this->increment('download_count');
    }

    public function incrementViews()
    {
        $this->increment('view_count');
    }
}
