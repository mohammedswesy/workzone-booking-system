<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id','workspace_id','title','discount_percent','starts_at','ends_at','is_active'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
    ];

    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function owner()     { return $this->belongsTo(User::class, 'owner_id'); }

public function scopeActive($q)
{
    $now = now();
    $q->where('is_active', true)
      ->where(function ($w) use ($now) {
          $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
      })
      ->where(function ($w) use ($now) {
          $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
      });
}

protected static function booted()
{
    static::creating(function ($offer) {
        if (empty($offer->owner_id) && ! empty($offer->workspace_id)) {
            $offer->owner_id = \App\Models\Workspace::where('id', $offer->workspace_id)
                               ->value('owner_id');
        }
    });

     static::updating(function (Offer $offer) {
            if ($offer->isDirty('workspace_id')) {
                $offer->owner_id = Workspace::where('id', $offer->workspace_id)
                    ->value('owner_id');
            }
        });
    

}



}
