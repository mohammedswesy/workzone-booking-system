<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly MarkBookingPaid $markPaid,
        private readonly ?PayPalClient $client = null,
    ) {}

    public function initiate(Booking $booking, array $payload = []): PaymentResult
    {
        if (! config('payments.providers.paypal.enabled')) {
            throw new RuntimeException('PayPal payments are disabled.');
        }

        $reference = 'paypal-'.$booking->id.'-'.Str::lower(Str::random(10));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => PaymentProvider::Paypal,
            'reference' => $reference,
            'amount' => $booking->total_price,
            'currency' => config('payments.currency', 'USD'),
            'status' => PaymentStatus::Pending,
            'metadata' => [],
        ]);

        $order = $this->paypal()->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'amount' => [
                    'currency_code' => $payment->currency,
                    'value' => number_format((float) $payment->amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => route('payments.paypal.return'),
                'cancel_url' => route('payments.paypal.cancel'),
            ],
        ]);

        if (! isset($order['id'])) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => ['error' => $order],
            ]);
            $booking->update(['payment_status' => PaymentStatus::Failed]);

            throw new RuntimeException('Unable to create PayPal order.');
        }

        $approve = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        $payment->update([
            'metadata' => [
                'paypal_order_id' => $order['id'],
            ],
        ]);

        $booking->update(['payment_status' => PaymentStatus::Pending]);

        return new PaymentResult(
            payment: $payment->fresh(),
            redirectUrl: $approve,
            message: 'Redirect to PayPal to complete payment.',
        );
    }

    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        if ($payment->status === PaymentStatus::Paid) {
            return new PaymentResult($payment, message: 'Already paid.');
        }

        $orderId = $payload['paypal_order_id']
            ?? ($payment->metadata['paypal_order_id'] ?? null);

        if (! $orderId) {
            throw new RuntimeException('Missing PayPal order id.');
        }

        $captured = $this->paypal()->capturePaymentOrder($orderId);

        $status = strtoupper((string) ($captured['status'] ?? ''));
        if ($status !== 'COMPLETED') {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($payment->metadata ?? [], ['capture' => $captured]),
            ]);
            $payment->booking?->update(['payment_status' => PaymentStatus::Failed]);

            throw new RuntimeException('PayPal capture was not completed.');
        }

        $payment = $this->markPaid->handle($payment, [
            'paypal_order_id' => $orderId,
            'capture' => $captured,
        ]);

        return new PaymentResult($payment, message: 'PayPal payment captured.');
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $payload = $request->all();
        $eventType = (string) ($payload['event_type'] ?? '');
        $resource = $payload['resource'] ?? [];

        $orderId = $resource['id']
            ?? data_get($resource, 'supplementary_data.related_ids.order_id')
            ?? data_get($resource, 'purchase_units.0.payments.captures.0.id');

        $reference = data_get($resource, 'purchase_units.0.reference_id')
            ?? data_get($payload, 'resource.reference_id');

        $payment = null;
        if ($reference) {
            $payment = Payment::query()
                ->where('provider', PaymentProvider::Paypal)
                ->where('reference', $reference)
                ->first();
        }

        if (! $payment && $orderId) {
            $payment = Payment::query()
                ->where('provider', PaymentProvider::Paypal)
                ->where('metadata->paypal_order_id', $orderId)
                ->first();
        }

        if (! $payment) {
            Log::warning('PayPal webhook for unknown payment', ['payload' => $payload]);

            throw new RuntimeException('Payment not found for webhook.');
        }

        // Idempotent success path.
        if ($payment->status === PaymentStatus::Paid) {
            return new PaymentResult($payment, message: 'Webhook ignored; already paid.');
        }

        if (! $this->verifyWebhook($request)) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'webhook_rejected' => true,
                    'event_type' => $eventType,
                ]),
            ]);

            throw new RuntimeException('Invalid PayPal webhook signature.');
        }

        $completedEvents = [
            'CHECKOUT.ORDER.APPROVED',
            'CHECKOUT.ORDER.COMPLETED',
            'PAYMENT.CAPTURE.COMPLETED',
        ];

        if (! in_array($eventType, $completedEvents, true)) {
            return new PaymentResult($payment, message: 'Webhook acknowledged without status change.');
        }

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            return $this->confirm($payment, [
                'paypal_order_id' => $payment->metadata['paypal_order_id'] ?? $orderId,
            ]);
        }

        $payment = $this->markPaid->handle($payment, [
            'webhook_event' => $eventType,
            'webhook' => $payload,
        ]);

        return new PaymentResult($payment, message: 'Webhook processed.');
    }

    private function paypal(): PayPalClient
    {
        if ($this->client) {
            return $this->client;
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        return $provider;
    }

    private function verifyWebhook(Request $request): bool
    {
        $webhookId = config('payments.providers.paypal.webhook_id');

        // In local/testing without webhook id, allow faked gateways injected via container.
        if (app()->environment('testing') && ! $webhookId) {
            return (bool) $request->header('X-PayPal-Webhook-Valid', false);
        }

        if (! $webhookId) {
            return false;
        }

        try {
            $result = $this->paypal()->verifyWebHook([
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

            return strtoupper((string) ($result['verification_status'] ?? '')) === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
