<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with('faculty')
            ->withCount(['students', 'teachers', 'courses']);

        if ($request->has('faculty_id')) {
            $query->where('faculty_id', $request->faculty_id);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        $departments = $query->orderBy('name')->get();

        return $this->success($departments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'faculty_id' => 'required|exists:faculties,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments',
            'description' => 'nullable|string',
            'head_name' => 'nullable|string|max:255',
        ]);

        $department = Department::create($request->all());

        ActivityLog::log('create', 'Created department: ' . $department->name, $department);

        return $this->success($department->load('faculty'), 'Department created successfully', 201);
    }

    public function show(Department $department)
    {
        $department->load(['faculty', 'courses', 'students.user', 'teachers.user']);
        
        return $this->success($department);
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'faculty_id' => 'sometimes|exists:faculties,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:departments,code,' . $department->id,
            'description' => 'nullable|string',
            'head_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $department->toArray();
        $department->update($request->all());

        ActivityLog::log('update', 'Updated department: ' . $department->name, $department, $oldValues, $department->toArray());

        return $this->success($department->load('faculty'), 'Department updated successfully');
    }

    public function destroy(Department $department)
    {
        if ($department->students()->count() > 0) {
            return $this->error('Cannot delete department with students', 400);
        }

        if ($department->courses()->count() > 0) {
            return $this->error('Cannot delete department with courses', 400);
        }

        ActivityLog::log('delete', 'Deleted department: ' . $department->name, $department);
        
        $department->delete();

        return $this->success(null, 'Department deleted successfully');
    }

    public function toggle(Department $department)
    {
        $department->update(['is_active' => !$department->is_active]);

        $action = $department->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('toggle', "Department {$action}: " . $department->name, $department);

        return $this->success($department, "Department {$action} successfully");
    }
}
