<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Old venue slug → current venue (301 redirects after slug edits).
 */
class VenueSlugRedirect extends Model
{
    protected $fillable = [
        'venue_id',
        'slug',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
