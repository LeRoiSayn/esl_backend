<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AcademicLevelController extends Controller
{
    public function index()
    {
        return $this->success(AcademicLevel::orderBy('order')->orderBy('code')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'  => 'required|string|max:10|unique:academic_levels,code',
            'label' => 'required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        $level = AcademicLevel::create([
            'code'      => strtoupper($request->code),
            'label'     => $request->label,
            'order'     => $request->order ?? AcademicLevel::max('order') + 1,
            'is_active' => true,
        ]);

        ActivityLog::log('create', "Created academic level: {$level->code}", $level);

        return $this->success($level, 'Niveau créé', 201);
    }

    public function update(Request $request, AcademicLevel $academicLevel)
    {
        $request->validate([
            'code'      => 'sometimes|string|max:10|unique:academic_levels,code,' . $academicLevel->id,
            'label'     => 'sometimes|string|max:100',
            'order'     => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $academicLevel->update($request->only(['code', 'label', 'order', 'is_active']));

        ActivityLog::log('update', "Updated academic level: {$academicLevel->code}", $academicLevel);

        return $this->success($academicLevel, 'Niveau mis à jour');
    }

    public function destroy(AcademicLevel $academicLevel)
    {
        // Check if any course uses this level
        $inUse = \App\Models\Course::where('level', $academicLevel->code)->exists();
        if ($inUse) {
            return $this->error('Ce niveau est utilisé par des cours existants', 400);
        }

        ActivityLog::log('delete', "Deleted academic level: {$academicLevel->code}", $academicLevel);
        $academicLevel->delete();

        return $this->success(null, 'Niveau supprimé');
    }

    public function toggle(AcademicLevel $academicLevel)
    {
        $academicLevel->update(['is_active' => !$academicLevel->is_active]);
        $action = $academicLevel->is_active ? 'activé' : 'désactivé';
        ActivityLog::log('toggle', "Academic level {$action}: {$academicLevel->code}", $academicLevel);

        return $this->success($academicLevel, "Niveau {$action}");
    }

    /**
     * Reorder levels. Body: [{ id: 1, order: 0 }, ...]
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'levels'         => 'required|array',
            'levels.*.id'    => 'required|exists:academic_levels,id',
            'levels.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->levels as $item) {
            AcademicLevel::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return $this->success(AcademicLevel::orderBy('order')->get(), 'Ordre mis à jour');
    }
}
