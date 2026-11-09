<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\FleetCar;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarRentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_are_created_with_sanitized_uppercase_driver_licenses(): void
    {
        $this->postJson('/customers', [
            'driver_license' => '  <b>ab-123</b> ',
            'customer_name' => '  Taylor Driver  ',
            'customer_phone' => ' 555-0100 ',
        ])->assertCreated()
            ->assertJsonPath('data.driver_license', 'AB-123')
            ->assertJsonPath('data.customer_name', 'Taylor Driver')
            ->assertJsonPath('data.customer_phone', '555-0100');
    }

    public function test_duplicate_licenses_are_validated_after_normalization(): void
    {
        Customer::create([
            'driver_license' => 'AB-123',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);

        $this->postJson('/customers', [
            'driver_license' => ' ab-123 ',
            'customer_name' => 'Another Driver',
            'customer_phone' => '555-0101',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('driver_license');
    }

    public function test_fleet_cars_are_created_with_sanitized_uppercase_vins(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => '  <b>abc123</b> ',
            'car_model_id' => $carModel->id,
        ])->assertCreated()
            ->assertJsonPath('data.vehicle_vin', 'ABC123');

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => ' abc123 ',
            'car_model_id' => $carModel->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_vin');
    }

    public function test_rental_totals_are_calculated_and_overlapping_bookings_are_rejected(): void
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

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => '2026-12-01',
            'rental_end_date' => '2026-12-04',
            'insurance_fee' => '12.50',
        ])->assertCreated()
            ->assertJsonPath('data.total_days', 3)
            ->assertJsonPath('data.total_rental_cost', '253.25');

        $this->assertSame(1, Rental::count());

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => '2026-12-03',
            'rental_end_date' => '2026-12-06',
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fleet_car_id');

        $this->assertSame(1, Rental::count());
    }

    public function test_vehicle_customer_and_rental_creation_forms_are_available(): void
    {
        $this->get('/fleet-cars/create')->assertOk();
        $this->get('/customers/create')->assertOk();
        $this->get('/rentals/create')->assertOk();
    }
}
