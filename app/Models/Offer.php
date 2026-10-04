<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'workspace_id',
        'title',
        'discount_percent',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'discount_percent' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where(function (Builder $w) use ($at) {
                $w->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $w) use ($at) {
                $w->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function overlapsAnother(): bool
    {
        $start = $this->starts_at;
        $end = $this->ends_at;

        return static::query()
            ->where('workspace_id', $this->workspace_id)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->id))
            ->where('is_active', true)
            ->where(function (Builder $q) use ($start, $end) {
                // existing.start < new.end (null start => -∞)
                $q->where(function (Builder $w) use ($end) {
                    if ($end === null) {
                        $w->whereRaw('1 = 1');
                    } else {
                        $w->whereNull('starts_at')->orWhere('starts_at', '<', $end);
                    }
                });

                // existing.end > new.start (null end => +∞)
                $q->where(function (Builder $w) use ($start) {
                    if ($start === null) {
                        $w->whereRaw('1 = 1');
                    } else {
                        $w->whereNull('ends_at')->orWhere('ends_at', '>', $start);
                    }
                });
            })
            ->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (Offer $offer) {
            if (empty($offer->owner_id) && ! empty($offer->workspace_id)) {
                $offer->owner_id = Workspace::where('id', $offer->workspace_id)->value('owner_id');
            }
        });

        static::updating(function (Offer $offer) {
            if ($offer->isDirty('workspace_id')) {
                $offer->owner_id = Workspace::where('id', $offer->workspace_id)->value('owner_id');
            }
        });
    }
}
