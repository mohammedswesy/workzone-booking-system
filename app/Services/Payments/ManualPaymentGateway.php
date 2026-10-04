<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
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
        $method = (string) ($payload['method'] ?? '');
        $workspace = $booking->workspace()->first() ?? $booking->workspace;

        if (! $workspace || ! $workspace->acceptsPaymentMethod($method)) {
            throw ValidationException::withMessages([
                'method' => 'This payment method is not accepted for this workspace.',
            ]);
        }

        /** @var UploadedFile|null $proof */
        $proof = $payload['proof'] ?? null;
        $isCash = $method === PaymentMethod::Cash->value;

        if (! $isCash && ! $proof instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'proof' => 'Payment proof file is required for this method.',
            ]);
        }

        $path = $proof instanceof UploadedFile
            ? $proof->store('payment-proofs', 'local')
            : null;

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'manual-'.$booking->id.'-'.uniqid(),
            'amount' => $booking->total_price,
            'currency' => config('payments.currency', 'USD'),
            'status' => PaymentStatus::Pending,
            'proof_path' => $path,
            'rejection_reason' => null,
            'metadata' => [
                'method' => $method,
                'original_name' => $proof?->getClientOriginalName(),
                'uploaded_by' => $payload['user_id'] ?? null,
            ],
        ]);

        $booking->update(['payment_status' => PaymentStatus::Pending]);

        return new PaymentResult(
            payment: $payment,
            message: $isCash && ! $path
                ? 'Cash payment noted. Waiting for owner confirmation.'
                : 'Proof uploaded. Waiting for owner confirmation.',
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

        return new PaymentResult($payment, message: 'Payment confirmed.');
    }

    public function reject(Payment $payment, string $reason, ?int $rejectedBy = null): PaymentResult
    {
        if ($payment->provider !== PaymentProvider::Manual) {
            throw new RuntimeException('Only manual payments can be rejected here.');
        }

        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'Paid payments cannot be rejected.',
            ]);
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'rejection_reason' => $reason,
            'metadata' => array_merge($payment->metadata ?? [], [
                'rejected_by' => $rejectedBy,
                'rejected_at' => now()->toIso8601String(),
            ]),
        ]);

        $payment->booking?->update([
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        return new PaymentResult(
            payment: $payment->fresh(),
            message: 'Payment proof rejected.',
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        throw new RuntimeException('Manual payments do not support webhooks.');
    }
}
