<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FleetCarController;
use App\Http\Controllers\RentalController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';


// Fleet Car Routes
Route::get('/fleet-cars', [FleetCarController::class, 'index'])
    ->name('fleet-cars.index');

Route::get('/fleet-cars/create', [FleetCarController::class, 'create'])
    ->name('fleet-cars.create');

Route::post('/fleet-cars', [FleetCarController::class, 'store'])
    ->name('fleet-cars.store');

Route::put('/fleet-cars/{id}', [FleetCarController::class, 'update'])
    ->name('fleet-cars.update');

// Rental Routes
Route::get('/rentals', [RentalController::class, 'index'])
    ->name('rentals.index');

Route::get('/rentals/create', [RentalController::class, 'create'])
    ->name('rentals.create');

Route::post('/rentals', [RentalController::class, 'store'])
    ->name('rentals.store');

Route::put('/rentals/{id}', [RentalController::class, 'update'])
    ->name('rentals.update');