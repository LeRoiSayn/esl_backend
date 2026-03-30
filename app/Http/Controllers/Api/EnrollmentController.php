<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Enrollment::with(['student.user', 'class.course', 'class.teacher.user']);

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($enrollments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        // Check if already enrolled
        $exists = Enrollment::where('student_id', $request->student_id)
            ->where('class_id', $request->class_id)
            ->exists();

        if ($exists) {
            return $this->error('Student is already enrolled in this class', 400);
        }

        $enrollment = Enrollment::create([
            'student_id' => $request->student_id,
            'class_id' => $request->class_id,
            'enrollment_date' => now(),
            'status' => 'enrolled',
        ]);

        ActivityLog::log('create', 'Student enrolled in class', $enrollment);

        return $this->success(
            $enrollment->load(['student.user', 'class.course']),
            'Student enrolled successfully',
            201
        );
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load([
            'student.user',
            'class.course',
            'class.teacher.user',
            'grades',
            'attendance',
        ]);
        
        return $this->success($enrollment);
    }

    public function updateStatus(Request $request, Enrollment $enrollment)
    {
        $request->validate([
            'status' => 'required|in:enrolled,dropped,completed',
        ]);

        $enrollment->update(['status' => $request->status]);

        ActivityLog::log('update', "Enrollment status changed to: {$request->status}", $enrollment);

        return $this->success($enrollment, 'Enrollment status updated');
    }

    public function destroy(Enrollment $enrollment)
    {
        if ($enrollment->grades()->count() > 0 || $enrollment->attendance()->count() > 0) {
            return $this->error('Cannot delete enrollment with grades or attendance records', 400);
        }

        ActivityLog::log('delete', 'Enrollment deleted', $enrollment);
        
        $enrollment->delete();

        return $this->success(null, 'Enrollment deleted successfully');
    }
}
