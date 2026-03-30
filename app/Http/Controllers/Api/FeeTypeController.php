<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
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

        $feeTypes = $query->orderBy('name')->get();

        return $this->success($feeTypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'is_mandatory' => 'boolean',
            'level' => 'nullable|in:L1,L2,L3,M1,M2,D1,D2,D3',
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
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'is_mandatory' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'level' => 'nullable|in:L1,L2,L3,M1,M2,D1,D2,D3',
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
