<?php

namespace App\Services;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\FleetCar;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CarRentalService
{
    /*
    |--------------------------------------------------------------------------
    | Sanitize string data
    |--------------------------------------------------------------------------
    */

    private static function cleanString(string $value): string
    {
        return trim(strip_tags($value));
    }

    public static function normalizeVehicleVin(string $value): string
    {
        return strtoupper(self::cleanString($value));
    }

    public static function normalizeDriverLicense(string $value): string
    {
        return strtoupper(self::cleanString($value));
    }

    public function createCustomer(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            return Customer::create([
                'driver_license' => self::normalizeDriverLicense($data['driver_license']),
                'customer_name' => self::cleanString($data['customer_name']),
                'customer_phone' => self::cleanString($data['customer_phone']),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Create a fleet car
    |--------------------------------------------------------------------------
    */

    public function createFleetCar(array $data): FleetCar
    {
        return DB::transaction(function () use ($data) {
            $data['vehicle_vin'] = self::normalizeVehicleVin($data['vehicle_vin']);

            return FleetCar::create([
                'vehicle_vin' => $data['vehicle_vin'],
                'car_model_id' => $data['car_model_id'],
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Update a fleet car
    |--------------------------------------------------------------------------
    */

    public function updateFleetCar(int $id, array $data): FleetCar
    {
        return DB::transaction(function () use ($id, $data) {
            $fleetCar = FleetCar::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $fleetCar->update([
                'vehicle_vin' => self::normalizeVehicleVin($data['vehicle_vin']),
                'car_model_id' => $data['car_model_id'],
            ]);

            return $fleetCar->fresh('carModel');
        });
    }

    public function updateCarModel(int $id, array $data): CarModel
    {
        return DB::transaction(function () use ($id, $data) {
            $carModel = CarModel::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $carModel->update([
                'model_name' => self::cleanString($data['model_name']),
                'daily_rate' => $data['daily_rate'],
            ]);

            return $carModel->fresh();
        });
    }

    public function createCarModel(array $data): CarModel
    {
        return DB::transaction(function () use ($data) {
            return CarModel::create([
                'model_name' => self::cleanString($data['model_name']),
                'daily_rate' => $data['daily_rate'],
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate rental days
    |--------------------------------------------------------------------------
    */

    private function calculateTotalDays(
        string $startDate,
        string $endDate
    ): int {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'rental_end_date' =>
                    'The rental end date must be after the start date.',
            ]);
        }

        return (int) $start->diffInDays($end);
    }

    /*
    |--------------------------------------------------------------------------
    | Check overlapping bookings
    |--------------------------------------------------------------------------
    */

    private function ensureVehicleIsAvailable(
        int $fleetCarId,
        string $startDate,
        string $endDate,
        ?int $excludeRentalId = null
    ): void {
        $query = Rental::query()
            ->where('fleet_car_id', $fleetCarId)
            ->where('rental_start_date', '<', $endDate)
            ->where('rental_end_date', '>', $startDate);

        if ($excludeRentalId !== null) {
            $query->where('id', '!=', $excludeRentalId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'fleet_car_id' =>
                    'This vehicle already has a booking during the selected dates.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create a rental
    |--------------------------------------------------------------------------
    */

    public function createRental(array $data): Rental
    {
        return DB::transaction(function () use ($data) {

            /*
             * Lock the vehicle row so concurrent booking requests
             * for the same vehicle are less likely to conflict.
             */

            $fleetCar = FleetCar::query()
                ->whereKey($data['fleet_car_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureVehicleIsAvailable(
                $fleetCar->id,
                $data['rental_start_date'],
                $data['rental_end_date']
            );

            /*
             * Retrieve the current daily rate from the car model.
             */

            $carModel = CarModel::query()
                ->findOrFail($fleetCar->car_model_id);

            $totalDays = $this->calculateTotalDays(
                $data['rental_start_date'],
                $data['rental_end_date']
            );

            $dailyRate = (float) $carModel->daily_rate;
            $insuranceFee = (float) $data['insurance_fee'];

            $totalCost = round(
                ($dailyRate * $totalDays) + $insuranceFee,
                2
            );

            return Rental::create([
                'customer_id' => $data['customer_id'],
                'fleet_car_id' => $fleetCar->id,
                'rental_start_date' => $data['rental_start_date'],
                'rental_end_date' => $data['rental_end_date'],
                'total_days' => $totalDays,
                'daily_rate' => $dailyRate,
                'insurance_fee' => $insuranceFee,
                'total_rental_cost' => $totalCost,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Update a rental
    |--------------------------------------------------------------------------
    */

    public function updateRental(int $id, array $data): Rental
    {
        return DB::transaction(function () use ($id, $data) {

            /*
             * Lock the current rental first.
             */

            $rental = Rental::query()
                ->lockForUpdate()
                ->findOrFail($id);

            /*
             * Lock the vehicle that the rental will use.
             */

            $fleetCar = FleetCar::query()
                ->whereKey($data['fleet_car_id'])
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Exclude this rental from the overlap check.
             */

            $this->ensureVehicleIsAvailable(
                $fleetCar->id,
                $data['rental_start_date'],
                $data['rental_end_date'],
                $rental->id
            );

            $carModel = CarModel::query()
                ->findOrFail($fleetCar->car_model_id);

            $totalDays = $this->calculateTotalDays(
                $data['rental_start_date'],
                $data['rental_end_date']
            );

            $dailyRate = (float) $carModel->daily_rate;
            $insuranceFee = (float) $data['insurance_fee'];

            $totalCost = round(
                ($dailyRate * $totalDays) + $insuranceFee,
                2
            );

            $rental->update([
                'customer_id' => $data['customer_id'],
                'fleet_car_id' => $fleetCar->id,
                'rental_start_date' => $data['rental_start_date'],
                'rental_end_date' => $data['rental_end_date'],
                'total_days' => $totalDays,
                'daily_rate' => $dailyRate,
                'insurance_fee' => $insuranceFee,
                'total_rental_cost' => $totalCost,
            ]);

            return $rental->fresh([
                'customer',
                'fleetCar.carModel',
                'payments',
            ]);
        });
    }
}