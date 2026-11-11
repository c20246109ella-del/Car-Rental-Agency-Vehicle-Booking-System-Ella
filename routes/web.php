<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarModelController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FleetCarController;
use App\Http\Controllers\RentalController;
use App\Models\CarModel;
use App\Models\FleetCar;
use App\Models\Rental;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $today = now()->toDateString();

        $recentRentals = Rental::with([
            'customer:id,customer_name',
            'fleetCar:id,vehicle_vin,car_model_id',
            'fleetCar.carModel:id,model_name',
        ])
            ->latest()
            ->take(5)
            ->get()
            ->map(static function (Rental $rental) use ($today): array {
                $startDate = $rental->rental_start_date->toDateString();
                $endDate = $rental->rental_end_date->toDateString();

                return [
                    'id' => $rental->id,
                    'customerId' => $rental->customer_id,
                    'customerName' => $rental->customer->customer_name,
                    'fleetCarId' => $rental->fleet_car_id,
                    'carModel' => $rental->fleetCar->carModel->model_name,
                    'vehicleVin' => $rental->fleetCar->vehicle_vin,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'insuranceFee' => $rental->insurance_fee,
                    'totalCost' => $rental->total_rental_cost,
                    'status' => $today < $startDate ? 'Upcoming' : ($today < $endDate ? 'Active' : 'Completed'),
                ];
            });

        $fleetCars = FleetCar::with('carModel:id,model_name')
            ->orderBy('vehicle_vin')
            ->get(['id', 'vehicle_vin', 'car_model_id'])
            ->map(static fn ($fleetCar): array => [
                'id' => $fleetCar->id,
                'vehicleVin' => $fleetCar->vehicle_vin,
                'carModel' => $fleetCar->carModel->model_name,
                'carModelId' => $fleetCar->car_model_id,
            ]);

        return Inertia::render('dashboard', [
            'recentRentals' => $recentRentals,
            'fleetCars' => $fleetCars,
            'carModels' => CarModel::orderBy('model_name')
                ->get(['id', 'model_name'])
                ->map(static fn (CarModel $carModel): array => [
                    'id' => $carModel->id,
                    'modelName' => $carModel->model_name,
                ]),
            'today' => $today,
        ]);
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::get('/customers/create', [CustomerController::class, 'create'])
    ->name('customers.create');

Route::post('/customers', [CustomerController::class, 'store'])
    ->name('customers.store');

// Fleet Car Routes
Route::get('/fleet-cars', [FleetCarController::class, 'index'])
    ->name('fleet-cars.index');

Route::get('/fleet-cars/create', [FleetCarController::class, 'create'])
    ->name('fleet-cars.create');

Route::post('/fleet-cars', [FleetCarController::class, 'store'])
    ->name('fleet-cars.store');

Route::put('/fleet-cars/{id}', [FleetCarController::class, 'update'])
    ->name('fleet-cars.update');

Route::put('/car-models/{id}', [CarModelController::class, 'update'])
    ->name('car-models.update');

Route::post('/car-models', [CarModelController::class, 'store'])
    ->name('car-models.store');

// Rental Routes
Route::get('/rentals', [RentalController::class, 'index'])
    ->name('rentals.index');

Route::get('/rentals/create', [RentalController::class, 'create'])
    ->name('rentals.create');

Route::post('/rentals', [RentalController::class, 'store'])
    ->name('rentals.store');

Route::put('/rentals/{id}', [RentalController::class, 'update'])
    ->name('rentals.update');