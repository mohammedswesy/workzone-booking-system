<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Services\Payments\ManualPaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\PaymentsConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function storeManual(Request $request, Booking $booking)
    {
        $this->authorizeBookingPayment($request, $booking);

        $activeIds = PlatformPaymentMethod::query()->active()->pluck('id')->all();

        $data = $request->validate([
            'platform_payment_method_id' => ['required', 'integer', Rule::in($activeIds)],
            'transfer_reference' => ['nullable', 'string', 'max:64'],
            'proof' => ['nullable', 'file', 'max:5120'],
        ]);

        $result = $this->gateways->driver(PaymentProvider::Manual)->initiate($booking, [
            'platform_payment_method_id' => $data['platform_payment_method_id'],
            'transfer_reference' => $data['transfer_reference'] ?? null,
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

    public function showProof(Request $request, Payment $payment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $payment->loadMissing('booking');
        $booking = $payment->booking;
        abort_unless($booking, 404);

        // Owners must never see proof files — only the booker and admins.
        $allowed = $user->isAdmin() || $booking->user_id === $user->id;
        abort_unless($allowed, 403);
        abort_unless(filled($payment->proof_path), 404);

        $path = $payment->proof_path;
        $disk = Storage::disk('local')->exists($path)
            ? 'local'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        abort_unless($disk !== null, 404);

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return Storage::disk($disk)->response(
            $path,
            $filename,
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function confirmManual(Request $request, Payment $payment)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        abort_unless($payment->provider === PaymentProvider::Manual, 422);

        $data = $request->validate([
            'received_amount' => ['required', 'numeric', 'min:0.01'],
            'amount_disposition' => ['nullable', 'in:partial,overpaid'],
            'amount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->gateways->driver(PaymentProvider::Manual)->confirm($payment, [
            'confirmed_by' => $user->id,
            'received_amount' => $data['received_amount'],
            'amount_disposition' => $data['amount_disposition'] ?? null,
            'amount_note' => $data['amount_note'] ?? null,
        ]);

        return back()->with('success', $result->message);
    }

    public function rejectManual(Request $request, Payment $payment, ManualPaymentGateway $manual)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
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
