<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function initiate(Booking $booking, array $payload = []): PaymentResult;

    public function confirm(Payment $payment, array $payload = []): PaymentResult;

    public function handleWebhook(Request $request): PaymentResult;
}
