<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    //
      use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];


    protected $fillable = [
        'user_id',
        'workspace_id',
        'hours',
        'total_price',
        'status',
    ];

    
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function workspace()
    // {
    //     return $this->belongsTo(Workspace::class.'workspace_id');
    // }

    public function user()
{
    return $this->belongsTo(User::class,);
}

public function workspace()
{
    return $this->belongsTo(Workspace::class,);
}

}
