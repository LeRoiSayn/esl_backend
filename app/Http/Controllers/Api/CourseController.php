<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\AcademicLevel;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('department.faculty');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('code')->paginate($request->per_page ?? 15);

        return $this->success($courses);
    }

    public function store(Request $request)
    {
        $levelCodes = AcademicLevel::activeCodes();
        $request->validate([
            'department_id'  => 'required|exists:departments,id',
            'code'           => 'required|string|max:20|unique:courses',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'credits'        => 'required|integer|min:1|max:30',
            'level'          => ['required', 'string', 'in:' . implode(',', $levelCodes)],
            'course_type'    => 'nullable|in:tronc_commun,specialisation',
            'hours_per_week' => 'nullable|integer|min:1|max:20',
        ]);

        $course = Course::create($request->all());

        ActivityLog::log('create', "Created course: {$course->code} - {$course->name}", $course);

        return $this->success($course->load('department.faculty'), 'Course created successfully', 201);
    }

    public function show(Course $course)
    {
        $course->load(['department.faculty', 'classes.teacher.user', 'classes.enrollments']);
        
        return $this->success($course);
    }

    public function update(Request $request, Course $course)
    {
        $levelCodes = AcademicLevel::activeCodes();
        $request->validate([
            'department_id'  => 'sometimes|exists:departments,id',
            'code'           => 'sometimes|string|max:20|unique:courses,code,' . $course->id,
            'name'           => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'credits'        => 'sometimes|integer|min:1|max:30',
            'level'          => ['sometimes', 'string', 'in:' . implode(',', $levelCodes)],
            'course_type'    => 'nullable|in:tronc_commun,specialisation',
            'hours_per_week' => 'nullable|integer|min:1|max:20',
            'is_active'      => 'sometimes|boolean',
        ]);

        $oldValues = $course->toArray();
        $course->update($request->all());

        ActivityLog::log('update', "Updated course: {$course->code}", $course, $oldValues, $course->toArray());

        return $this->success($course->load('department.faculty'), 'Course updated successfully');
    }

    public function destroy(Course $course)
    {
        if ($course->classes()->count() > 0) {
            return $this->error('Cannot delete course with classes', 400);
        }

        ActivityLog::log('delete', "Deleted course: {$course->code} - {$course->name}", $course);
        
        $course->delete();

        return $this->success(null, 'Course deleted successfully');
    }

    public function toggle(Course $course)
    {
        $course->update(['is_active' => !$course->is_active]);

        $action = $course->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('toggle', "Course {$action}: {$course->code}", $course);

        return $this->success($course, "Course {$action} successfully");
    }
}
