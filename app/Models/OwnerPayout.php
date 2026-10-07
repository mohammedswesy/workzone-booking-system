<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnerPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'amount_requested',
        'amount_approved',
        'currency',
        'status',
        'payout_method',
        'payout_account_holder',
        'payout_account_identifier',
        'transfer_reference',
        'paid_at',
        'owner_note',
        'admin_note',
        'rejection_reason',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_requested' => 'decimal:2',
            'amount_approved' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(OwnerLedgerEntry::class, 'payout_id');
    }

    public function effectiveAmount(): string
    {
        $amount = $this->amount_approved ?? $this->amount_requested;

        return bcadd((string) $amount, '0', 2);
    }
}
