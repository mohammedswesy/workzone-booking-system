<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitHour extends Model
{
    protected $fillable = [
        'workspace_id',
        'weekday',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
