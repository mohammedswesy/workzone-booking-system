<?php

namespace App\Models;

use App\Enums\AvailabilityExceptionType;
use App\Enums\AvailabilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    protected $fillable = [
        'scope',
        'venue_id',
        'workspace_id',
        'starts_on',
        'ends_on',
        'type',
        'reason',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => AvailabilityScope::class,
            'type' => AvailabilityExceptionType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function coversDate(string $ymd): bool
    {
        $day = $ymd;

        return $this->starts_on->toDateString() <= $day
            && $this->ends_on->toDateString() >= $day;
    }
}
