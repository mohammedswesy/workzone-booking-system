<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManualPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly MarkBookingPaid $markPaid,
    ) {}

    public function initiate(Booking $booking, array $payload = []): PaymentResult
    {
        /** @var UploadedFile|null $proof */
        $proof = $payload['proof'] ?? null;

        if (! $proof instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'proof' => 'Payment proof file is required.',
            ]);
        }

        $path = $proof->store('payment-proofs', 'public');

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'manual-'.$booking->id.'-'.uniqid(),
            'amount' => $booking->total_price,
            'currency' => config('payments.currency', 'USD'),
            'status' => PaymentStatus::Pending,
            'proof_path' => $path,
            'metadata' => [
                'original_name' => $proof->getClientOriginalName(),
                'uploaded_by' => $payload['user_id'] ?? null,
            ],
        ]);

        $booking->update(['payment_status' => PaymentStatus::Pending]);

        return new PaymentResult(
            payment: $payment,
            message: 'Proof uploaded. Waiting for owner/admin confirmation.',
        );
    }

    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        if ($payment->provider !== PaymentProvider::Manual) {
            throw new RuntimeException('Only manual payments can be confirmed here.');
        }

        if ($payment->status === PaymentStatus::Paid) {
            return new PaymentResult($payment, message: 'Already paid.');
        }

        $payment = $this->markPaid->handle($payment, [
            'confirmed_by' => $payload['confirmed_by'] ?? null,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return new PaymentResult($payment, message: 'Manual payment confirmed.');
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        throw new RuntimeException('Manual payments do not support webhooks.');
    }
}
