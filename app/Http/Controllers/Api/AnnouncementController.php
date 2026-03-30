<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with('author');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('audience')) {
            $query->forAudience($request->audience);
        }

        $announcements = $query->orderBy('publish_date', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($announcements);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,academic,financial,event',
            'target_audience' => 'required|in:all,students,teachers,staff',
            'publish_date' => 'required|date',
            'expire_date' => 'nullable|date|after:publish_date',
        ]);

        $announcement = Announcement::create([
            ...$request->all(),
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', "Created announcement: {$announcement->title}", $announcement);

        return $this->success($announcement->load('author'), 'Announcement created successfully', 201);
    }

    public function show(Announcement $announcement)
    {
        $announcement->load('author');
        
        return $this->success($announcement);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => 'sometimes|in:general,academic,financial,event',
            'target_audience' => 'sometimes|in:all,students,teachers,staff',
            'publish_date' => 'sometimes|date',
            'expire_date' => 'nullable|date|after:publish_date',
            'is_active' => 'sometimes|boolean',
        ]);

        $announcement->update($request->all());

        ActivityLog::log('update', "Updated announcement: {$announcement->title}", $announcement);

        return $this->success($announcement->load('author'), 'Announcement updated successfully');
    }

    public function destroy(Announcement $announcement)
    {
        ActivityLog::log('delete', "Deleted announcement: {$announcement->title}", $announcement);
        
        $announcement->delete();

        return $this->success(null, 'Announcement deleted successfully');
    }

    public function active(Request $request)
    {
        $user = $request->user();
        $audience = match($user->role) {
            'student' => 'students',
            'teacher' => 'teachers',
            default => 'staff',
        };

        $announcements = Announcement::active()
            ->forAudience($audience)
            ->with('author')
            ->orderBy('publish_date', 'desc')
            ->take(10)
            ->get();

        return $this->success($announcements);
    }
}
