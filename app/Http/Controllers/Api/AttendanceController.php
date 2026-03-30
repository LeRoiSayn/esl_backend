<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['enrollment.student.user', 'enrollment.class.course', 'markedBy']);

        if ($request->has('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        if ($request->has('class_id')) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $attendance = $query->orderBy('date', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($attendance);
    }

    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused',
            'remarks' => 'nullable|string',
        ]);

        // Check if already marked
        $exists = Attendance::where('enrollment_id', $request->enrollment_id)
            ->whereDate('date', $request->date)
            ->exists();

        if ($exists) {
            return $this->error('Attendance already marked for this date', 400);
        }

        $attendance = Attendance::create([
            'enrollment_id' => $request->enrollment_id,
            'date' => $request->date,
            'status' => $request->status,
            'remarks' => $request->remarks,
            'marked_by' => auth()->id(),
        ]);

        ActivityLog::log('create', 'Attendance marked', $attendance);

        return $this->success(
            $attendance->load(['enrollment.student.user']),
            'Attendance marked successfully',
            201
        );
    }

    public function show(Attendance $attendance)
    {
        $attendance->load(['enrollment.student.user', 'enrollment.class.course', 'markedBy']);
        
        return $this->success($attendance);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $request->validate([
            'status' => 'sometimes|in:present,absent,late,excused',
            'remarks' => 'nullable|string',
        ]);

        $attendance->update([
            'status' => $request->status ?? $attendance->status,
            'remarks' => $request->remarks ?? $attendance->remarks,
            'marked_by' => auth()->id(),
        ]);

        ActivityLog::log('update', 'Attendance updated', $attendance);

        return $this->success($attendance, 'Attendance updated successfully');
    }

    public function destroy(Attendance $attendance)
    {
        ActivityLog::log('delete', 'Attendance deleted', $attendance);
        
        $attendance->delete();

        return $this->success(null, 'Attendance deleted successfully');
    }

    public function byClass(int $classId, Request $request)
    {
        $date = $request->date ?? now()->toDateString();

        $enrollments = Enrollment::with(['student.user'])
            ->where('class_id', $classId)
            ->where('status', 'enrolled')
            ->get();

        $attendance = Attendance::whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereDate('date', $date)
            ->get()
            ->keyBy('enrollment_id');

        $result = $enrollments->map(function ($enrollment) use ($attendance, $date) {
            return [
                'enrollment_id' => $enrollment->id,
                'student' => $enrollment->student,
                'attendance' => $attendance->get($enrollment->id),
                'date' => $date,
            ];
        });

        return $this->success($result);
    }

    public function bulkMark(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.enrollment_id' => 'required|exists:enrollments,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused',
        ]);

        $marked = 0;
        foreach ($request->attendance as $record) {
            Attendance::updateOrCreate(
                [
                    'enrollment_id' => $record['enrollment_id'],
                    'date' => $request->date,
                ],
                [
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => auth()->id(),
                ]
            );
            $marked++;
        }

        ActivityLog::log('bulk_mark', "Bulk marked attendance for {$marked} students");

        return $this->success(['marked' => $marked], "Attendance marked for {$marked} students");
    }

    public function statistics(int $classId)
    {
        $enrollments = Enrollment::where('class_id', $classId)
            ->where('status', 'enrolled')
            ->pluck('id');

        $stats = Attendance::whereIn('enrollment_id', $enrollments)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $total = $stats->sum();

        return $this->success([
            'total_records' => $total,
            'present' => $stats->get('present', 0),
            'absent' => $stats->get('absent', 0),
            'late' => $stats->get('late', 0),
            'excused' => $stats->get('excused', 0),
            'attendance_rate' => $total > 0 
                ? round((($stats->get('present', 0) + $stats->get('late', 0)) / $total) * 100, 2) 
                : 0,
        ]);
    }
}
