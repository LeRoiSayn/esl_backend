<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
use App\Models\AcademicLevel;
use App\Models\SystemSetting;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class FeeTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = FeeType::query();

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        if ($request->has('academic_year') && $request->academic_year) {
            $query->where(function ($q) use ($request) {
                $q->where('academic_year', $request->academic_year)
                  ->orWhereNull('academic_year');
            });
        }

        if ($request->has('level') && $request->level) {
            $query->where(function ($q) use ($request) {
                $q->where('level', $request->level)
                  ->orWhereNull('level');
            });
        }

        $feeTypes = $query->orderBy('name')->get();

        return $this->success($feeTypes);
    }

    public function store(Request $request)
    {
        $levelCodes  = AcademicLevel::activeCodes();
        $categories  = SystemSetting::get('fee_categories', ['tuition', 'registration', 'library', 'lab', 'other']);
        $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'amount'        => 'required|numeric|min:0',
            'is_mandatory'  => 'boolean',
            'category'      => ['nullable', 'string', 'in:' . implode(',', $categories)],
            'level'         => ['nullable', 'string', 'in:' . implode(',', $levelCodes)],
            'academic_year' => 'nullable|string|max:10|regex:/^\d{4}-\d{4}$/',
        ]);

        $feeType = FeeType::create($request->all());

        ActivityLog::log('create', "Created fee type: {$feeType->name}", $feeType);

        return $this->success($feeType, 'Fee type created successfully', 201);
    }

    public function show(FeeType $feeType)
    {
        return $this->success($feeType);
    }

    public function update(Request $request, FeeType $feeType)
    {
        $levelCodes  = AcademicLevel::activeCodes();
        $categories  = SystemSetting::get('fee_categories', ['tuition', 'registration', 'library', 'lab', 'other']);
        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'amount'        => 'sometimes|numeric|min:0',
            'is_mandatory'  => 'sometimes|boolean',
            'is_active'     => 'sometimes|boolean',
            'category'      => ['nullable', 'string', 'in:' . implode(',', $categories)],
            'level'         => ['nullable', 'string', 'in:' . implode(',', $levelCodes)],
            'academic_year' => 'nullable|string|max:10|regex:/^\d{4}-\d{4}$/',
        ]);

        $oldValues = $feeType->toArray();
        $feeType->update($request->all());

        ActivityLog::log('update', "Updated fee type: {$feeType->name}", $feeType, $oldValues, $feeType->toArray());

        return $this->success($feeType, 'Fee type updated successfully');
    }

    public function destroy(FeeType $feeType)
    {
        if ($feeType->studentFees()->count() > 0) {
            return $this->error('Cannot delete fee type with assigned fees', 400);
        }

        ActivityLog::log('delete', "Deleted fee type: {$feeType->name}", $feeType);
        
        $feeType->delete();

        return $this->success(null, 'Fee type deleted successfully');
    }

    public function toggle(FeeType $feeType)
    {
        $feeType->update(['is_active' => !$feeType->is_active]);

        $action = $feeType->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('toggle', "Fee type {$action}: {$feeType->name}", $feeType);

        return $this->success($feeType, "Fee type {$action} successfully");
    }
}
