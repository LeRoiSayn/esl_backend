<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_fee_id',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->reference_number)) {
                $payment->reference_number = 'PAY-' . strtoupper(Str::random(10));
            }
        });

        static::created(function ($payment) {
            $fee = $payment->studentFee;
            $fee->paid_amount = (float)$fee->paid_amount + (float)$payment->amount;

            // Auto-sync installment plan: mark tranches as paid based on total paid_amount.
            // Handles overpayments correctly — excess credits the next installment.
            if (!empty($fee->installment_plan['installments'])) {
                $plan = $fee->installment_plan;
                $basePaid  = (float)($plan['base_paid_amount'] ?? 0);
                $remaining = max(0.0, $fee->paid_amount - $basePaid);

                foreach ($plan['installments'] as &$inst) {
                    $amt = (float)$inst['amount'];
                    if ($remaining >= $amt) {
                        $inst['paid'] = true;
                        $remaining  -= $amt;
                    } else {
                        $inst['paid'] = false;
                    }
                }
                unset($inst);
                $fee->installment_plan = $plan;
            }

            $fee->updateStatus(); // calls save() internally
        });
    }

    public function studentFee()
    {
        return $this->belongsTo(StudentFee::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
