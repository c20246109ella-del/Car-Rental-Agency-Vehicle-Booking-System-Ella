<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_license',
        'customer_name',
        'customer_phone',
    ];

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}