<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    public function index()
    {
        $faculties = Faculty::withCount('departments')
            ->orderBy('name')
            ->get();

        return $this->success($faculties);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:faculties',
            'description' => 'nullable|string',
            'dean_name' => 'nullable|string|max:255',
        ]);

        $faculty = Faculty::create($request->all());

        ActivityLog::log('create', 'Created faculty: ' . $faculty->name, $faculty);

        return $this->success($faculty, 'Faculty created successfully', 201);
    }

    public function show(Faculty $faculty)
    {
        $faculty->load('departments');
        
        return $this->success($faculty);
    }

    public function update(Request $request, Faculty $faculty)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:faculties,code,' . $faculty->id,
            'description' => 'nullable|string',
            'dean_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $faculty->toArray();
        $faculty->update($request->all());

        ActivityLog::log('update', 'Updated faculty: ' . $faculty->name, $faculty, $oldValues, $faculty->toArray());

        return $this->success($faculty, 'Faculty updated successfully');
    }

    public function destroy(Faculty $faculty)
    {
        if ($faculty->departments()->count() > 0) {
            return $this->error('Cannot delete faculty with departments', 400);
        }

        ActivityLog::log('delete', 'Deleted faculty: ' . $faculty->name, $faculty);
        
        $faculty->delete();

        return $this->success(null, 'Faculty deleted successfully');
    }

    public function toggle(Faculty $faculty)
    {
        $faculty->update(['is_active' => !$faculty->is_active]);

        $action = $faculty->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('toggle', "Faculty {$action}: " . $faculty->name, $faculty);

        return $this->success($faculty, "Faculty {$action} successfully");
    }
}
