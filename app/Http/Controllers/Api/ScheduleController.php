<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ClassModel;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = Schedule::with(['class.course', 'class.teacher.user']);

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }

        $schedules = $query->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->orderBy('start_time')
            ->get();

        return $this->success($schedules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:50',
            'midterm_date' => 'nullable|date',
            'final_date' => 'nullable|date',
        ]);

        // Check for same-class time conflict
        $conflict = Schedule::where('class_id', $request->class_id)
            ->where('day_of_week', $request->day_of_week)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($conflict) {
            return $this->error('Schedule conflict detected for this class', 400);
        }

        // Check for teacher-level time conflict (teacher cannot teach two classes at the same time)
        $classModel = ClassModel::find($request->class_id);
        if ($classModel && $classModel->teacher_id) {
            $teacherClassIds = ClassModel::where('teacher_id', $classModel->teacher_id)
                ->where('id', '!=', $request->class_id)
                ->pluck('id');

            $teacherConflict = Schedule::whereIn('class_id', $teacherClassIds)
                ->where('day_of_week', $request->day_of_week)
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($teacherConflict) {
                return $this->error('The assigned teacher already has another class scheduled at this time', 400);
            }
        }

        $schedule = Schedule::create($request->all());

        ActivityLog::log('create', 'Schedule created', $schedule);

        return $this->success($schedule->load(['class.course']), 'Schedule created successfully', 201);
    }

    public function show(Schedule $schedule)
    {
        $schedule->load(['class.course', 'class.teacher.user']);
        
        return $this->success($schedule);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $request->validate([
            'day_of_week' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:50',
            'midterm_date' => 'nullable|date',
            'final_date' => 'nullable|date',
        ]);

        $dayOfWeek = $request->day_of_week ?? $schedule->day_of_week;
        $startTime = $request->start_time ?? $schedule->start_time;
        $endTime = $request->end_time ?? $schedule->end_time;

        // Check same-class conflict (exclude current schedule)
        $conflict = Schedule::where('class_id', $schedule->class_id)
            ->where('id', '!=', $schedule->id)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($conflict) {
            return $this->error('Schedule conflict detected for this class', 400);
        }

        // Check teacher-level conflict (exclude current schedule)
        $classModel = $schedule->class ?? ClassModel::find($schedule->class_id);
        if ($classModel && $classModel->teacher_id) {
            $teacherClassIds = ClassModel::where('teacher_id', $classModel->teacher_id)
                ->where('id', '!=', $schedule->class_id)
                ->pluck('id');

            $teacherConflict = Schedule::whereIn('class_id', $teacherClassIds)
                ->where('day_of_week', $dayOfWeek)
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->exists();

            if ($teacherConflict) {
                return $this->error('The assigned teacher already has another class scheduled at this time', 400);
            }
        }

        $schedule->update($request->all());

        ActivityLog::log('update', 'Schedule updated', $schedule);

        return $this->success($schedule->load(['class.course']), 'Schedule updated successfully');
    }

    public function destroy(Schedule $schedule)
    {
        ActivityLog::log('delete', 'Schedule deleted', $schedule);
        
        $schedule->delete();

        return $this->success(null, 'Schedule deleted successfully');
    }

    public function byStudent(int $studentId)
    {
        $enrollmentIds = \App\Models\Enrollment::where('student_id', $studentId)
            ->where('status', 'enrolled')
            ->pluck('class_id');

        $schedules = Schedule::with(['class.course', 'class.teacher.user'])
            ->whereIn('class_id', $enrollmentIds)
            ->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->orderBy('start_time')
            ->get();

        return $this->success($schedules);
    }

    public function byTeacher(int $teacherId)
    {
        $classIds = \App\Models\ClassModel::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->pluck('id');

        $schedules = Schedule::with(['class.course'])
            ->whereIn('class_id', $classIds)
            ->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->orderBy('start_time')
            ->get();

        return $this->success($schedules);
    }
}
