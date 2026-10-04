<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\PaymentsConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function storeManual(Request $request, Booking $booking)
    {
        $this->authorizeBookingPayment($request, $booking);

        $booking->loadMissing('workspace');

        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'proof' => [
                Rule::requiredIf(fn () => $request->string('method')->toString() !== PaymentMethod::Cash->value),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:4096',
            ],
        ]);

        $result = $this->gateways->driver(PaymentProvider::Manual)->initiate($booking, [
            'method' => $data['method'],
            'proof' => $request->file('proof'),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', $result->message);
    }

    public function storePaypal(Request $request, Booking $booking)
    {
        $this->authorizeBookingPayment($request, $booking);

        abort_unless(
            PaymentsConfig::paypalAvailable(),
            422,
            'PayPal is not configured.',
        );

        $result = $this->gateways->driver(PaymentProvider::Paypal)->initiate($booking);

        if ($result->redirectUrl) {
            return redirect()->away($result->redirectUrl);
        }

        return back()->with('success', $result->message);
    }

    public function paypalReturn(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $payment = Payment::query()
            ->where('provider', PaymentProvider::Paypal)
            ->where('metadata->paypal_order_id', $request->string('token')->toString())
            ->firstOrFail();

        abort_unless($payment->booking?->user_id === $request->user()?->id || $request->user()?->isAdmin(), 403);

        $result = $this->gateways->driver(PaymentProvider::Paypal)->confirm($payment, [
            'paypal_order_id' => $request->string('token')->toString(),
        ]);

        return redirect()
            ->route('user.bookings.show', $payment->booking_id)
            ->with('success', $result->message);
    }

    public function paypalCancel()
    {
        return redirect()
            ->route('user.bookings.index')
            ->with('error', 'PayPal payment was cancelled.');
    }

    public function confirmManual(Request $request, Payment $payment)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $payment->load('booking.workspace');

        $isOwner = $payment->booking?->workspace?->owner_id === $user->id;
        abort_unless($user->isAdmin() || $isOwner, 403);
        abort_unless($payment->provider === PaymentProvider::Manual, 422);

        $result = $this->gateways->driver(PaymentProvider::Manual)->confirm($payment, [
            'confirmed_by' => $user->id,
        ]);

        return back()->with('success', $result->message);
    }

    public function rejectManual(Request $request, Payment $payment, ManualPaymentGateway $manual)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $payment->load('booking.workspace');

        $isOwner = $payment->booking?->workspace?->owner_id === $user->id;
        abort_unless($user->isAdmin() || $isOwner, 403);
        abort_unless($payment->provider === PaymentProvider::Manual, 422);
        abort_unless($payment->status === PaymentStatus::Pending, 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $result = $manual->reject($payment, $data['reason'], $user->id);

        return back()->with('success', $result->message);
    }

    public function webhookPaypal(Request $request)
    {
        $result = $this->gateways->driver(PaymentProvider::Paypal)->handleWebhook($request);

        return response()->json([
            'ok' => true,
            'payment_id' => $result->payment->id,
            'status' => $result->payment->status->value,
            'message' => $result->message,
        ]);
    }

    private function authorizeBookingPayment(Request $request, Booking $booking): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($booking->user_id === $user->id || $user->role === Role::Admin, 403);
        abort_unless($booking->status === BookingStatus::Pending, 422);
        abort_unless($booking->payment_status !== PaymentStatus::Paid, 422);
    }
}
