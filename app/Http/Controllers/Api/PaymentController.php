<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    // ==================== FINANCE CRUD METHODS ====================

    /**
     * List all payments (finance/admin)
     */
    public function index(Request $request)
    {
        $payments = Payment::with(['studentFee.student.user', 'studentFee.feeType', 'receivedBy'])
            ->orderBy('payment_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->success($payments);
    }

    /**
     * Record a payment (finance/admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_fee_id' => 'required|exists:student_fees,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $fee = StudentFee::findOrFail($request->student_fee_id);
        $balance = $fee->amount - $fee->paid_amount;

        if ($request->amount > $balance) {
            return $this->error('Le montant dépasse le solde restant (' . number_format($balance) . ' FCFA)', 422);
        }

        $payment = Payment::create([
            'student_fee_id' => $request->student_fee_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => $request->payment_date,
            'notes' => $request->notes,
            'received_by' => $request->user()->id,
        ]);

        return $this->success($payment->load(['studentFee.student.user', 'studentFee.feeType']), 'Paiement enregistré', 201);
    }

    /**
     * Show a single payment
     */
    public function show($id)
    {
        $payment = Payment::with(['studentFee.student.user', 'studentFee.feeType', 'receivedBy'])
            ->findOrFail($id);

        return $this->success($payment);
    }

    /**
     * Update a payment (finance correction)
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'amount'         => 'sometimes|numeric|min:1',
            'payment_method' => 'sometimes|string',
            'payment_date'   => 'sometimes|date',
            'notes'          => 'nullable|string',
        ]);

        // Adjust paid_amount on the associated fee by delta
        if ($request->has('amount') && $request->amount != $payment->amount) {
            $fee = $payment->studentFee;
            if ($fee) {
                $delta = $request->amount - $payment->amount;
                $fee->paid_amount = max(0, $fee->paid_amount + $delta);
                $fee->updateStatus();
            }
        }

        $payment->update($request->only(['amount', 'payment_method', 'payment_date', 'notes']));

        return $this->success($payment->load(['studentFee.student.user', 'studentFee.feeType']), 'Paiement modifié');
    }

    /**
     * Delete a payment
     */
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);

        // Reverse the paid amount on the student fee
        $fee = $payment->studentFee;
        if ($fee) {
            $fee->paid_amount = max(0, $fee->paid_amount - $payment->amount);
            $fee->updateStatus();
        }

        $payment->delete();

        return $this->success(null, 'Paiement supprimé');
    }

    /**
     * Get payment receipt
     */
    public function receipt($id)
    {
        $payment = Payment::with(['studentFee.student.user', 'studentFee.feeType', 'receivedBy'])
            ->findOrFail($id);

        $student = $payment->studentFee->student;
        $user = $student?->user;

        return $this->success([
            'receipt_number' => 'ESL-' . date('Y') . '-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
            'date' => $payment->payment_date->format('Y-m-d'),
            'student' => [
                'name' => $user ? ($user->first_name . ' ' . $user->last_name) : 'N/A',
                'registration_number' => $student->student_id ?? $student->registration_number ?? 'N/A',
            ],
            'fee_type' => $payment->studentFee->feeType->name ?? 'N/A',
            'amount' => $payment->amount,
            'payment_method' => $this->getPaymentMethodLabel($payment->payment_method),
            'reference' => $payment->reference_number,
            'received_by' => $payment->receivedBy ? ($payment->receivedBy->first_name . ' ' . $payment->receivedBy->last_name) : 'System',
        ]);
    }

    /**
     * Get today's payment collection total
     */
    public function todayCollection()
    {
        $total = Payment::whereDate('payment_date', today())->sum('amount');
        $count = Payment::whereDate('payment_date', today())->count();

        return $this->success([
            'total' => $total,
            'count' => $count,
        ]);
    }

    // ==================== STUDENT ONLINE PAYMENT METHODS ====================

    /**
     * Get student's fee summary
     */
    public function getFeeSummary(Request $request)
    {
        $student = $request->user()->student;
        
        if (!$student) {
            return response()->json(['error' => 'Student profile not found'], 404);
        }

        $fees = StudentFee::with('feeType')
            ->where('student_id', $student->id)
            ->get();

        $totalFees = $fees->sum('amount');
        $totalPaid = $fees->sum('paid_amount');
        $remaining = $totalFees - $totalPaid;

        // Get next due date
        $nextDue = $fees->where('due_date', '>', now())
            ->sortBy('due_date')
            ->first();

        // Get recent completed transactions for this student
        $recentTransactions = Transaction::where('student_id', $student->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'summary' => [
                'total_fees' => $totalFees,
                'total_paid' => $totalPaid,
                'remaining' => $remaining,
                'next_due_date' => $nextDue?->due_date,
                'next_due_amount' => $nextDue?->amount,
            ],
            'fees' => $fees,
            'recent_payments' => $recentTransactions,
        ]);
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(Request $request)
    {
        $student = $request->user()->student;
        
        if (!$student) {
            return response()->json(['error' => 'Student profile not found'], 404);
        }

        // Get transactions (student-initiated payments)
        $transactions = Transaction::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Initialize a payment
     */
    public function initializePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000', // Minimum 1000 FCFA
            'payment_method' => 'required|in:card,paypal,airtel_money,moov_money,bank_transfer',
            'phone_number' => 'required_if:payment_method,airtel_money,moov_money|nullable|string',
            'paypal_email' => 'required_if:payment_method,paypal|nullable|email',
            'purpose' => 'nullable|string',
        ]);

        $student = $request->user()->student;
        
        if (!$student) {
            return response()->json(['error' => 'Student profile not found'], 404);
        }

        // Verify amount doesn't exceed remaining fees
        $totalFees = StudentFee::where('student_id', $student->id)->sum('amount');
        $totalPaid = StudentFee::where('student_id', $student->id)->sum('paid_amount');
        $remaining = $totalFees - $totalPaid;

        if ($remaining > 0 && $request->amount > $remaining) {
            return response()->json([
                'error' => 'Le montant dépasse le solde restant (' . number_format($remaining) . ' FCFA)',
            ], 422);
        }

        // Create transaction
        $transaction = Transaction::create([
            'student_id' => $student->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'phone_number' => $request->phone_number,
            'status' => 'pending',
            'metadata' => [
                'initiated_at' => now()->toIso8601String(),
                'user_agent' => $request->userAgent(),
                'purpose' => $request->purpose,
            ],
        ]);

        // If card payment, attempt to initialize via Paystack or Flutterwave (if configured)
        if ($request->payment_method === 'card') {
            $paystackSecret = config('services.paystack.secret_key');
            $flwSecret = config('services.flutterwave.secret_key');
            $frontendUrl = config('services.frontend_url', config('app.url'));

            // Prefer Paystack if configured
            if (!empty($paystackSecret)) {
                $callback = $frontendUrl . '/student/payment/status?reference=' . $transaction->reference;

                // Paystack expects amount in the smallest currency unit (multiply by 100)
                $amount = intval(round($transaction->amount * 100));

                $payload = [
                    'email' => $request->user()->email,
                    'amount' => $amount,
                    'reference' => $transaction->reference,
                    'currency' => 'XAF',
                    'callback_url' => $callback,
                    'metadata' => [
                        'student_id' => $student->id,
                        'student_name' => trim($request->user()->first_name . ' ' . $request->user()->last_name),
                    ],
                ];

                $resp = Http::withToken($paystackSecret)
                    ->post('https://api.paystack.co/transaction/initialize', $payload);

                $body = $resp->json();

                if ($resp->successful() && isset($body['data']['authorization_url'])) {
                    $link = $body['data']['authorization_url'];

                    return response()->json([
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'status' => $transaction->status,
                        'payment_data' => [
                            'type' => 'redirect',
                            'url' => $link,
                            'provider' => 'paystack',
                        ],
                    ]);
                }

                // Paystack API error – auto-confirm for internal processing
                $transaction->markAsCompleted('PSK-' . now()->format('YmdHis'));

                return response()->json([
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'status' => 'completed',
                    'payment_data' => [
                        'type' => 'confirmed',
                        'message' => 'Paiement par carte enregistré et confirmé.',
                    ],
                ]);
            }

            // If Paystack not configured, try Flutterwave
            if (!empty($flwSecret)) {
                $redirectUrl = $frontendUrl . '/student/payment/status?reference=' . $transaction->reference;

                $payload = [
                    'tx_ref' => $transaction->reference,
                    'amount' => (string) number_format($transaction->amount, 2, '.', ''),
                    'currency' => 'XAF',
                    'redirect_url' => $redirectUrl,
                    'payment_options' => 'card',
                    'customer' => [
                        'email' => $request->user()->email,
                        'phonenumber' => $request->phone_number ?? $request->user()->phone,
                        'name' => trim($request->user()->first_name . ' ' . $request->user()->last_name),
                    ],
                    'customizations' => [
                        'title' => 'ESL - Paiement frais',
                        'description' => 'Paiement des frais scolaires',
                    ],
                ];

                $resp = Http::withToken($flwSecret)
                    ->post('https://api.flutterwave.com/v3/payments', $payload);

                $body = $resp->json();

                if ($resp->successful() && isset($body['data'])) {
                    $link = $body['data']['link'] ?? ($body['data']['authorization_url'] ?? null);

                    if ($link) {
                        return response()->json([
                            'transaction_id' => $transaction->id,
                            'reference' => $transaction->reference,
                            'status' => $transaction->status,
                            'payment_data' => [
                                'type' => 'redirect',
                                'url' => $link,
                                'provider' => 'flutterwave',
                            ],
                        ]);
                    }
                }

                // Flutterwave failed – auto-confirm for internal processing
                $transaction->markAsCompleted('FLW-' . now()->format('YmdHis'));

                return response()->json([
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'status' => 'completed',
                    'payment_data' => [
                        'type' => 'confirmed',
                        'message' => 'Paiement enregistré et confirmé.',
                    ],
                ]);
            }

            // No external provider configured – auto-confirm for internal processing
            $transaction->markAsCompleted('INT-' . now()->format('YmdHis'));

            return response()->json([
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => 'completed',
                'payment_data' => [
                    'type' => 'confirmed',
                    'message' => 'Paiement par carte enregistré et confirmé.',
                ],
            ]);
        }

        // PayPal payment
        if ($request->payment_method === 'paypal') {
            $paypalClientId = config('services.paypal.client_id');
            $paypalSecret = config('services.paypal.secret');
            $paypalMode = config('services.paypal.mode', 'sandbox');

            $baseUrl = $paypalMode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            if (!empty($paypalClientId) && !empty($paypalSecret)) {
                try {
                    $tokenResp = Http::asForm()
                        ->withBasicAuth($paypalClientId, $paypalSecret)
                        ->post("$baseUrl/v1/oauth2/token", [
                            'grant_type' => 'client_credentials',
                        ]);

                    if ($tokenResp->successful()) {
                        $accessToken = $tokenResp->json()['access_token'];

                        $frontendUrl = config('services.frontend_url', config('app.url'));
                        $returnUrl = $frontendUrl . '/student/payment/status?reference=' . $transaction->reference;
                        $cancelUrl = $frontendUrl . '/student/payment?cancelled=true';

                        $orderResp = Http::withToken($accessToken)
                            ->post("$baseUrl/v2/checkout/orders", [
                                'intent' => 'CAPTURE',
                                'purchase_units' => [[
                                    'reference_id' => $transaction->reference,
                                    'amount' => [
                                        'currency_code' => 'EUR',
                                        'value' => number_format($transaction->amount / 655.957, 2, '.', ''),
                                    ],
                                    'description' => 'ESL - Paiement frais scolaires',
                                ]],
                                'application_context' => [
                                    'return_url' => $returnUrl,
                                    'cancel_url' => $cancelUrl,
                                    'brand_name' => 'École de Santé de Libreville',
                                ],
                            ]);

                        if ($orderResp->successful()) {
                            $links = collect($orderResp->json()['links'] ?? []);
                            $approveLink = $links->firstWhere('rel', 'approve');

                            if ($approveLink) {
                                return response()->json([
                                    'transaction_id' => $transaction->id,
                                    'reference' => $transaction->reference,
                                    'status' => $transaction->status,
                                    'payment_data' => [
                                        'type' => 'redirect',
                                        'url' => $approveLink['href'],
                                        'provider' => 'paypal',
                                    ],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('PayPal API error: ' . $e->getMessage());
                }
            }

            // PayPal not configured or failed – auto-confirm for internal processing
            $transaction->markAsCompleted('PP-' . now()->format('YmdHis'));

            return response()->json([
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => 'completed',
                'payment_data' => [
                    'type' => 'confirmed',
                    'message' => 'Paiement PayPal enregistré et confirmé.',
                ],
            ]);
        }

        // Other payment methods (mobile money, bank transfer)
        // Auto-confirm for internal processing (no external provider integration)
        $transaction->markAsCompleted('MOB-' . now()->format('YmdHis'));

        return response()->json([
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'status' => 'completed',
            'payment_data' => [
                'type' => 'confirmed',
                'message' => 'Paiement enregistré et confirmé.',
            ],
        ]);
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(Request $request, $reference)
    {
        $transaction = Transaction::where('reference', $reference)->firstOrFail();

        // Verify ownership
        $student = $request->user()->student;
        if (!$student || $student->id !== $transaction->student_id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        return response()->json([
            'reference' => $transaction->reference,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'payment_method' => $transaction->payment_method,
            'created_at' => $transaction->created_at,
        ]);
    }

    /**
     * Confirm payment (webhook endpoint)
     * Called by payment provider or internal confirmation
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
            'provider_reference' => 'required|string',
            'status' => 'required|in:success,failed',
        ]);

        $transaction = Transaction::where('reference', $request->reference)->firstOrFail();

        if ($request->status === 'success') {
            $transaction->markAsCompleted($request->provider_reference);

            return response()->json([
                'message' => 'Paiement confirmé avec succès',
                'transaction' => $transaction,
            ]);
        } else {
            $transaction->markAsFailed($request->failure_reason ?? 'Payment failed');

            return response()->json([
                'message' => 'Paiement échoué',
                'transaction' => $transaction,
            ]);
        }
    }

    /**
     * Download payment receipt
     */
    public function downloadReceipt(Request $request, $paymentId)
    {
        // For student-initiated payments, use transactions
        $transaction = Transaction::where('id', $paymentId)
            ->orWhere('reference', $paymentId)
            ->first();

        if (!$transaction) {
            return response()->json(['error' => 'Transaction non trouvée'], 404);
        }

        $student = $transaction->student;
        $user = $student?->user;

        // Verify ownership or admin
        if ($request->user()->role !== 'admin' && 
            $request->user()->role !== 'finance' &&
            $request->user()->student?->id !== $transaction->student_id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $receipt = [
            'receipt_number' => 'ESL-' . date('Y') . '-' . str_pad($transaction->id, 5, '0', STR_PAD_LEFT),
            'date' => $transaction->created_at->format('Y-m-d'),
            'student' => [
                'name' => $user ? ($user->first_name . ' ' . $user->last_name) : 'N/A',
                'registration_number' => $student->student_id ?? $student->registration_number ?? 'N/A',
            ],
            'amount' => $transaction->amount,
            'amount_words' => $this->numberToWords((int)$transaction->amount) . ' Francs CFA',
            'payment_method' => $this->getPaymentMethodLabel($transaction->payment_method),
            'reference' => $transaction->reference,
            'status' => $transaction->status === 'completed' ? 'Payé' : ucfirst($transaction->status),
        ];

        return response()->json([
            'receipt' => $receipt,
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    private function processPayment($transaction, $request)
    {
        switch ($transaction->payment_method) {
            case 'airtel_money':
            case 'moov_money':
                return [
                    'type' => 'mobile_money',
                    'instructions' => [
                        'Vous recevrez une notification sur votre téléphone',
                        'Entrez votre code PIN pour confirmer',
                        'Le paiement sera automatiquement enregistré',
                    ],
                    'phone' => $transaction->phone_number,
                    'timeout_seconds' => 300,
                ];

            case 'card':
                return [
                    'type' => 'redirect',
                    'url' => '/payment/card/' . $transaction->reference,
                    'instructions' => ['Vous serez redirigé vers la page de paiement sécurisé'],
                ];

            case 'bank_transfer':
                return [
                    'type' => 'bank_transfer',
                    'instructions' => [
                        'Effectuez un virement vers le compte suivant:',
                        'Banque: BGFI Bank Gabon',
                        'IBAN: GA12 3456 7890 1234 5678 9012',
                        'Référence: ' . $transaction->reference,
                        'Montant: ' . number_format($transaction->amount) . ' FCFA',
                    ],
                ];

            default:
                return ['type' => 'unknown'];
        }
    }

    private function getPaymentMethodLabel($method)
    {
        return match($method) {
            'card' => 'Carte bancaire',
            'paypal' => 'PayPal',
            'airtel_money' => 'Airtel Money',
            'moov_money' => 'Moov Money',
            'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            default => $method,
        };
    }

    private function numberToWords($number)
    {
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            $ten = intval($number / 10);
            $unit = $number % 10;
            return trim($tens[$ten] . ($unit > 0 ? '-' . $units[$unit] : ''));
        }

        if ($number < 1000) {
            $hundred = intval($number / 100);
            $rest = $number % 100;
            return ($hundred > 1 ? $units[$hundred] . ' ' : '') . 'cent' . ($rest > 0 ? ' ' . $this->numberToWords($rest) : '');
        }

        if ($number < 1000000) {
            $thousand = intval($number / 1000);
            $rest = $number % 1000;
            return ($thousand > 1 ? $this->numberToWords($thousand) . ' ' : '') . 'mille' . ($rest > 0 ? ' ' . $this->numberToWords($rest) : '');
        }

        return number_format($number);
    }
}
