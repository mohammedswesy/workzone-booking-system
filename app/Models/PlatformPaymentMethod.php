<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlatformPaymentMethod extends Model
{
    /** @use HasFactory<\Database\Factories\PlatformPaymentMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'label',
        'account_holder',
        'account_identifier',
        'qr_path',
        'note',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['qr_url'];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethod::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getQrUrlAttribute(): ?string
    {
        return PublicStorageUrl::fromPath($this->qr_path);
    }

    public function deleteQrFile(): void
    {
        if (filled($this->qr_path) && Storage::disk('public')->exists($this->qr_path)) {
            Storage::disk('public')->delete($this->qr_path);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function toBookerArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'label' => $this->label,
            'account_holder' => $this->account_holder,
            'account_identifier' => $this->account_identifier,
            'qr_url' => $this->qr_url,
            'note' => $this->note,
            'requires_reference' => ($this->type instanceof PaymentMethod
                ? $this->type
                : PaymentMethod::from((string) $this->type))->requiresReference(),
        ];
    }
}
