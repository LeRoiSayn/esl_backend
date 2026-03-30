<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 50);

        return $this->success($logs);
    }

    public function show(ActivityLog $activityLog)
    {
        $activityLog->load('user');
        
        return $this->success($activityLog);
    }

    public function actions()
    {
        $actions = ActivityLog::distinct('action')
            ->pluck('action')
            ->sort()
            ->values();

        return $this->success($actions);
    }
}
