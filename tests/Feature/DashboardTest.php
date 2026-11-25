<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\FleetCar;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_dashboard_rental_updates_are_saved_to_the_database(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-DASH-RENTAL',
            'customer_name' => 'Dashboard Renter',
            'customer_phone' => '555-0101',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Dashboard Sedan',
            'daily_rate' => 75,
        ]);
        $originalFleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-DASH-OLD',
            'car_model_id' => $carModel->id,
        ]);
        $updatedFleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-DASH-NEW',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $originalFleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDays(2)->toDateString(),
            'total_days' => 2,
            'daily_rate' => 75,
            'insurance_fee' => 10,
            'total_rental_cost' => 160,
        ]);

        $this->actingAs(User::factory()->create())
            ->putJson("/rentals/{$rental->id}", [
                'customer_id' => $customer->id,
                'fleet_car_id' => $updatedFleetCar->id,
                'rental_start_date' => now()->toDateString(),
                'rental_end_date' => now()->addDays(3)->toDateString(),
                'insurance_fee' => '20',
            ])
            ->assertOk();

        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'fleet_car_id' => $updatedFleetCar->id,
            'total_days' => 3,
            'total_rental_cost' => '245.00',
        ]);
    }

    public function test_dashboard_displays_the_latest_rentals(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-123',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-123',
            'car_model_id' => $carModel->id,
        ]);
        Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 3,
            'daily_rate' => 80.25,
            'insurance_fee' => 0,
            'total_rental_cost' => 240.75,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('recentRentals', 1)
                ->has('fleetCars', 1)
                ->where('fleetCars.0.vehicleVin', 'VIN-123')
                ->where('fleetCars.0.carModelId', $carModel->id)
                ->has('carModels', 1)
                ->where('carModels.0.modelName', 'Sedan')
                ->where('recentRentals.0.customerName', 'Taylor Driver')
                ->where('recentRentals.0.carModel', 'Sedan')
                ->where('recentRentals.0.status', 'Active'));
    }
}
