<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'fleet_car_id',
        'rental_start_date',
        'rental_end_date',
        'total_days',
        'daily_rate',
        'insurance_fee',
        'total_rental_cost',
    ];

    protected function casts(): array
    {
        return [
            'rental_start_date' => 'date',
            'rental_end_date' => 'date',
            'total_days' => 'integer',
            'daily_rate' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'total_rental_cost' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fleetCar(): BelongsTo
    {
        return $this->belongsTo(FleetCar::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }
}