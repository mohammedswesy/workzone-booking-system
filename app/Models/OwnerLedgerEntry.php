<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'owner_id',
        'booking_id',
        'payout_id',
        'type',
        'amount',
        'currency',
        'status',
        'note',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Ledger entries are immutable. Post an adjustment instead.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Ledger entries are immutable. Post an adjustment instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(OwnerPayout::class, 'payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
