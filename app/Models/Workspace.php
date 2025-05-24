<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    //
     use HasFactory;

    // الحقول القابلة للتعبئة
    protected $fillable = ['name', 'location', 'capacity','price_per_hour'];
    public function bookings()
{
    return $this->hasMany(Booking::class);
}

}
