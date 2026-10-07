<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'platform_payment_method_id',
        'provider',
        'reference',
        'transfer_reference',
        'amount',
        'currency',
        'status',
        'paid_at',
        'proof_path',
        'proof_sha256',
        'proof_upload_attempts',
        'received_amount',
        'amount_disposition',
        'amount_note',
        'rejection_reason',
        'metadata',
    ];

    protected $appends = [
        'proof_url',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function platformPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PlatformPaymentMethod::class);
    }

    public function getProofUrlAttribute(): ?string
    {
        if (! filled($this->proof_path)) {
            return null;
        }

        return route('payments.proof.show', $this);
    }
}
