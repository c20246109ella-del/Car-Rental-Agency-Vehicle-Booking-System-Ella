<?php

namespace App\Models;

use App\Models\CarModel;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetCar extends Model
{
    use HasFactory, SoftDeletes;

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