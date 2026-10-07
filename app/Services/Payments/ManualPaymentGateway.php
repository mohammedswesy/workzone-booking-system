<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Rules\TransferReferenceRule;
use App\Services\Audit\AuditLogger;
use App\Services\Media\SecureImageStore;
use App\Support\AppTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManualPaymentGateway implements PaymentGateway
{
    public const MAX_PROOF_ATTEMPTS_PER_BOOKING = 8;

    public function __construct(
        private readonly MarkBookingPaid $markPaid,
        private readonly PaymentStateMachine $states,
        private readonly SecureImageStore $images,
        private readonly AuditLogger $audit,
    ) {}

    public function initiate(Booking $booking, array $payload = []): PaymentResult
    {
        $methodId = (int) ($payload['platform_payment_method_id'] ?? 0);
        $transferReference = trim((string) ($payload['transfer_reference'] ?? ''));

        /** @var \App\Models\PlatformPaymentMethod|null $method */
        $method = \App\Models\PlatformPaymentMethod::query()
            ->active()
            ->whereKey($methodId)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'platform_payment_method_id' => 'Select a valid platform payment method.',
            ]);
        }

        $type = $method->type instanceof PaymentMethod
            ? $method->type
            : PaymentMethod::from((string) $method->type);

        /** @var UploadedFile|null $proof */
        $proof = $payload['proof'] ?? null;

        $requiresReference = $type->requiresReference();
        if ($requiresReference || $transferReference !== '') {
            validator(
                ['transfer_reference' => $transferReference],
                ['transfer_reference' => [new TransferReferenceRule($method->id, required: $requiresReference)]],
            )->validate();
        }

        $attemptCount = Payment::query()->where('booking_id', $booking->id)->count();
        if ($attemptCount >= self::MAX_PROOF_ATTEMPTS_PER_BOOKING) {
            throw ValidationException::withMessages([
                'proof' => 'Too many payment proof attempts for this booking.',
            ]);
        }

        $stored = null;
        $reuseWarning = null;
        if ($proof instanceof UploadedFile) {
            $stored = $this->images->store($proof, 'payment-proofs', 'local');
            $reuse = Payment::query()
                ->where('proof_sha256', $stored['sha256'])
                ->where('booking_id', '!=', $booking->id)
                ->exists();
            if ($reuse) {
                $reuseWarning = 'This proof image matches a file already used on another booking.';
            }
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'platform_payment_method_id' => $method->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'manual-'.$booking->id.'-'.uniqid(),
            'transfer_reference' => $transferReference !== '' ? $transferReference : null,
            'amount' => $booking->total_price,
            'currency' => config('payments.currency', 'USD'),
            'status' => PaymentStatus::Pending,
            'proof_path' => $stored['path'] ?? null,
            'proof_sha256' => $stored['sha256'] ?? null,
            'proof_upload_attempts' => $attemptCount + 1,
            'rejection_reason' => null,
            'metadata' => [
                'method' => $type->value,
                'method_label' => $method->label,
                'original_name' => $proof?->getClientOriginalName(),
                'uploaded_by' => $payload['user_id'] ?? null,
                'proof_reuse_warning' => $reuseWarning,
            ],
        ]);

        $booking->update(['payment_status' => PaymentStatus::Pending]);

        $message = $type->isCash() && ! $stored
            ? 'Cash payment noted. Waiting for admin confirmation.'
            : 'Payment submitted. Waiting for admin confirmation.';

        if ($reuseWarning) {
            $message .= ' Warning: '.$reuseWarning;
        }

        return new PaymentResult(payment: $payment, message: $message);
    }

    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        if ($payment->provider !== PaymentProvider::Manual) {
            throw new RuntimeException('Only manual payments can be confirmed here.');
        }

        $payment = $this->markPaid->handle($payment, [
            'confirmed_by' => $payload['confirmed_by'] ?? null,
            'confirmed_by_role' => 'admin',
            'received_amount' => $payload['received_amount'] ?? $payment->amount,
            'amount_disposition' => $payload['amount_disposition'] ?? null,
            'amount_note' => $payload['amount_note'] ?? null,
        ]);

        return new PaymentResult($payment, message: 'Payment confirmed.');
    }

    public function reject(Payment $payment, string $reason, ?int $rejectedBy = null): PaymentResult
    {
        if ($payment->provider !== PaymentProvider::Manual) {
            throw new RuntimeException('Only manual payments can be rejected here.');
        }

        $fresh = DB::transaction(function () use ($payment, $reason, $rejectedBy) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment' => 'Paid payments cannot be rejected.',
                ]);
            }

            $this->states->assertCanTransition($locked->status, PaymentStatus::Failed);

            $rejectedAt = AppTimezone::now();

            $locked->update([
                'status' => PaymentStatus::Failed,
                'rejection_reason' => $reason,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'rejected_by' => $rejectedBy,
                    'rejected_at' => AppTimezone::utcIso($rejectedAt),
                ]),
            ]);

            $locked->booking?->update([
                'payment_status' => PaymentStatus::Unpaid,
            ]);

            $this->audit->log(
                'payment.reject',
                actor: request()->user(),
                subject: $locked,
                oldValues: ['status' => PaymentStatus::Pending->value],
                newValues: ['status' => PaymentStatus::Failed->value, 'reason' => $reason],
            );

            return $locked->fresh();
        });

        return new PaymentResult(
            payment: $fresh,
            message: 'Payment proof rejected.',
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        throw new RuntimeException('Manual payments do not support webhooks.');
    }
}
