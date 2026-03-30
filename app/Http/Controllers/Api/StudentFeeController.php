<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentFee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class StudentFeeController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentFee::with(['student.user', 'feeType']);

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('fee_type_id')) {
            $query->where('fee_type_id', $request->fee_type_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        $fees = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->success($fees);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'academic_year' => 'required|string|max:20',
        ]);

        $studentFee = StudentFee::create([
            'student_id' => $request->student_id,
            'fee_type_id' => $request->fee_type_id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'academic_year' => $request->academic_year,
            'status' => 'pending',
        ]);

        ActivityLog::log('create', 'Student fee assigned', $studentFee);

        return $this->success(
            $studentFee->load(['student.user', 'feeType']),
            'Fee assigned successfully',
            201
        );
    }

    public function show(StudentFee $studentFee)
    {
        $studentFee->load(['student.user', 'feeType', 'payments']);
        
        return $this->success($studentFee);
    }

    public function update(Request $request, StudentFee $studentFee)
    {
        $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'due_date' => 'sometimes|date',
        ]);

        if ($studentFee->paid_amount > 0 && $request->has('amount') && $request->amount < $studentFee->paid_amount) {
            return $this->error('Amount cannot be less than paid amount', 400);
        }

        $studentFee->update($request->only(['amount', 'due_date']));
        $studentFee->updateStatus();

        ActivityLog::log('update', 'Student fee updated', $studentFee);

        return $this->success($studentFee->load(['student.user', 'feeType']), 'Fee updated successfully');
    }

    public function destroy(StudentFee $studentFee)
    {
        if ($studentFee->payments()->count() > 0) {
            return $this->error('Cannot delete fee with payments', 400);
        }

        ActivityLog::log('delete', 'Student fee deleted', $studentFee);
        
        $studentFee->delete();

        return $this->success(null, 'Fee deleted successfully');
    }

    public function byStudent(int $studentId)
    {
        $fees = StudentFee::with(['feeType', 'payments'])
            ->where('student_id', $studentId)
            ->orderBy('due_date', 'desc')
            ->get();

        $summary = [
            'total' => $fees->sum('amount'),
            'paid' => $fees->sum('paid_amount'),
            'balance' => $fees->sum('balance'),
            'pending_count' => $fees->where('status', '!=', 'paid')->count(),
        ];

        return $this->success([
            'fees' => $fees,
            'summary' => $summary,
        ]);
    }

    public function assignToAll(Request $request)
    {
        $request->validate([
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'academic_year' => 'required|string|max:20',
            'level' => 'nullable|in:L1,L2,L3,M1,M2,D1,D2,D3',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $query = \App\Models\Student::where('status', 'active');

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $students = $query->get();
        $assigned = 0;

        foreach ($students as $student) {
            // Check if fee already exists
            $exists = StudentFee::where('student_id', $student->id)
                ->where('fee_type_id', $request->fee_type_id)
                ->where('academic_year', $request->academic_year)
                ->exists();

            if (!$exists) {
                StudentFee::create([
                    'student_id' => $student->id,
                    'fee_type_id' => $request->fee_type_id,
                    'amount' => $request->amount,
                    'due_date' => $request->due_date,
                    'academic_year' => $request->academic_year,
                    'status' => 'pending',
                ]);
                $assigned++;
            }
        }

        ActivityLog::log('bulk_assign', "Assigned fee to {$assigned} students");

        return $this->success(['assigned' => $assigned], "Fee assigned to {$assigned} students");
    }

    /**
     * Attach an installment plan (JSON) to an existing fee record.
     * Does NOT create new fee records — splits the remaining balance only.
     */
    public function setInstallmentPlan(Request $request, StudentFee $studentFee)
    {
        $request->validate([
            'plan_type'  => 'required|in:monthly,quarterly',
            'periods'    => 'required|integer|min:2|max:24',
            'start_date' => 'required|date',
        ]);

        $remaining  = round((float) $studentFee->amount - (float) $studentFee->paid_amount, 2);
        $periods    = (int) $request->periods;
        $baseAmount = floor(($remaining / $periods) * 100) / 100;
        $lastAmount = round($remaining - $baseAmount * ($periods - 1), 2);

        $startDate    = \Carbon\Carbon::parse($request->start_date);
        $installments = [];

        for ($i = 0; $i < $periods; $i++) {
            $dueDate = $request->plan_type === 'monthly'
                ? $startDate->copy()->addMonths($i)
                : $startDate->copy()->addMonths($i * 3);

            $installments[] = [
                'number'   => $i + 1,
                'amount'   => ($i === $periods - 1) ? $lastAmount : $baseAmount,
                'due_date' => $dueDate->toDateString(),
                'paid'     => false,
            ];
        }

        $studentFee->installment_plan = [
            'plan_type'        => $request->plan_type,
            'periods'          => $periods,
            'base_paid_amount' => (float) $studentFee->paid_amount, // paid amount at plan creation time
            'installments'     => $installments,
        ];
        $studentFee->save();

        ActivityLog::log('update', "Installment plan ({$request->plan_type}, {$periods} tranches) set on fee #{$studentFee->id}");

        return $this->success(
            $studentFee->load(['student.user', 'feeType']),
            "Plan défini : {$periods} tranches de " . number_format($baseAmount, 0, '.', ' ') . " RWF."
        );
    }
}
