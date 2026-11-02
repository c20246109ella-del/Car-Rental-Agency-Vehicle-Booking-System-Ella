<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetCar extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_vin',
        'car_model_id',
    ];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}