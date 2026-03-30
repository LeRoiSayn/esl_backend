<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassModel::with(['course.department', 'teacher.user'])
            ->withCount(['enrollments' => function ($q) {
                $q->where('status', 'enrolled');
            }]);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        $classes = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($classes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'section' => 'required|string|max:10',
            'room' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1',
            'academic_year' => 'required|string|max:20',
            'semester' => 'required|in:1,2,3',
        ]);

        // Check for duplicate
        $exists = ClassModel::where('course_id', $request->course_id)
            ->where('section', $request->section)
            ->where('academic_year', $request->academic_year)
            ->where('semester', $request->semester)
            ->exists();

        if ($exists) {
            return $this->error('A class with this course, section, academic year and semester already exists', 400);
        }

        $class = ClassModel::create($request->all());

        ActivityLog::log('create', "Created class for course: {$class->course->code}", $class);

        return $this->success($class->load(['course', 'teacher.user']), 'Class created successfully', 201);
    }

    public function show(ClassModel $class)
    {
        $class->load([
            'course.department',
            'teacher.user',
            'enrollments.student.user',
            'schedules',
        ]);
        
        return $this->success($class);
    }

    public function update(Request $request, ClassModel $class)
    {
        $request->validate([
            'teacher_id' => 'nullable|exists:teachers,id',
            'section' => 'sometimes|string|max:10',
            'room' => 'nullable|string|max:50',
            'capacity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $class->toArray();
        $class->update($request->all());

        ActivityLog::log('update', "Updated class: {$class->course->code} - {$class->section}", $class, $oldValues, $class->toArray());

        return $this->success($class->load(['course', 'teacher.user']), 'Class updated successfully');
    }

    public function destroy(ClassModel $class)
    {
        if ($class->enrollments()->where('status', 'enrolled')->count() > 0) {
            return $this->error('Cannot delete class with active enrollments', 400);
        }

        // Remove non-active enrollments (dropped, completed, transfer) before deleting
        $class->enrollments()->whereIn('status', ['dropped', 'completed', 'transfer'])->delete();

        ActivityLog::log('delete', "Deleted class: {$class->course->code} - {$class->section}", $class);

        $class->delete();

        return $this->success(null, 'Class deleted successfully');
    }

    public function students(ClassModel $class)
    {
        $enrollments = $class->enrollments()
            ->with(['student.user', 'grades', 'attendance'])
            ->where('status', 'enrolled')
            ->get();

        return $this->success($enrollments);
    }

    public function assignTeacher(Request $request, ClassModel $class)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $class->update(['teacher_id' => $request->teacher_id]);

        ActivityLog::log('assign', "Assigned teacher to class: {$class->course->code}", $class);

        return $this->success($class->load(['course', 'teacher.user']), 'Teacher assigned successfully');
    }
}
