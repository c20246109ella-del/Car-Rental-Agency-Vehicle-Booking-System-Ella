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
                ->where('recentRentals.0.customerName', 'Taylor Driver')
                ->where('recentRentals.0.carModel', 'Sedan')
                ->where('recentRentals.0.status', 'Active'));
    }
}
