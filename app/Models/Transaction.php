<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'payment_id',
        'reference',
        'amount',
        'payment_method',
        'phone_number',
        'card_last_four',
        'provider_reference',
        'status',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = 'ESL-' . date('Y') . '-' . strtoupper(Str::random(8));
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function markAsCompleted($providerReference = null)
    {
        $this->status = 'completed';
        $this->provider_reference = $providerReference;
        $this->save();

        // Update the student's fee balances if applicable
        $this->updateStudentFeeBalances();

        return $this;
    }

    public function markAsFailed($reason = null)
    {
        $this->status = 'failed';
        $this->failure_reason = $reason;
        $this->save();
        return $this;
    }

    /**
     * When a transaction completes, distribute the amount across unpaid student fees
     */
    private function updateStudentFeeBalances()
    {
        $remainingAmount = $this->amount;

        // Get unpaid fees for this student, ordered by due date (oldest first)
        $unpaidFees = StudentFee::where('student_id', $this->student_id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();

        foreach ($unpaidFees as $fee) {
            if ($remainingAmount <= 0) break;

            $balance = $fee->amount - $fee->paid_amount;
            if ($balance <= 0) continue;

            $payAmount = min($remainingAmount, $balance);
            $fee->paid_amount += $payAmount;
            $fee->updateStatus();

            $remainingAmount -= $payAmount;
        }
    }
}
