<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\FleetCar;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_fleet_cars_can_be_updated(): void
    {
        $originalModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $updatedModel = CarModel::create([
            'model_name' => 'SUV',
            'daily_rate' => 100,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-OLD',
            'car_model_id' => $originalModel->id,
        ]);

        $this->putJson("/fleet-cars/{$fleetCar->id}", [
            'vehicle_vin' => ' vin-new ',
            'car_model_id' => $updatedModel->id,
        ])->assertOk()
            ->assertJsonPath('data.vehicle_vin', 'VIN-NEW')
            ->assertJsonPath('data.car_model_id', $updatedModel->id);

        $this->assertDatabaseHas('fleet_cars', [
            'id' => $fleetCar->id,
            'vehicle_vin' => 'VIN-NEW',
            'car_model_id' => $updatedModel->id,
        ]);
    }

    public function test_car_models_can_be_updated_with_validated_details(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);

        $this->putJson("/car-models/{$carModel->id}", [
            'model_name' => '  Premium Sedan  ',
            'daily_rate' => '95.50',
        ])->assertOk()
            ->assertJsonPath('data.model_name', 'Premium Sedan')
            ->assertJsonPath('data.daily_rate', '95.50');

        $this->assertDatabaseHas('car_models', [
            'id' => $carModel->id,
            'model_name' => 'Premium Sedan',
            'daily_rate' => '95.50',
        ]);

        $this->putJson("/car-models/{$carModel->id}", [
            'model_name' => '',
            'daily_rate' => '95.501',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['model_name', 'daily_rate']);
    }

    public function test_car_models_can_be_created_with_validated_details(): void
    {
        $this->postJson('/car-models', [
            'model_name' => '  Compact Sedan  ',
            'daily_rate' => '69.99',
        ])->assertCreated()
            ->assertJsonPath('data.model_name', 'Compact Sedan')
            ->assertJsonPath('data.daily_rate', '69.99');

        $this->assertDatabaseHas('car_models', [
            'model_name' => 'Compact Sedan',
            'daily_rate' => '69.99',
        ]);

        $this->postJson('/car-models', [
            'model_name' => '',
            'daily_rate' => '-1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['model_name', 'daily_rate']);
    }

    public function test_fleet_car_creation_page_lists_existing_vehicles_for_editing(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        FleetCar::create([
            'vehicle_vin' => 'VIN-123',
            'car_model_id' => $carModel->id,
        ]);

        $this->get('/fleet-cars/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('fleet-cars/create')
                ->has('fleetCars', 1)
                ->where('fleetCars.0.vehicleVin', 'VIN-123')
                ->where('fleetCars.0.carModelId', $carModel->id));
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

    public function test_rental_dates_in_the_past_are_rejected(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-PAST',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-PAST',
            'car_model_id' => $carModel->id,
        ]);

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->subDay()->toDateString(),
            'rental_end_date' => now()->addDay()->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_start_date');

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->subDay()->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_end_date');
    }

    public function test_rental_period_cannot_exceed_fourteen_days(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-LIMIT',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-LIMIT',
            'car_model_id' => $carModel->id,
        ]);
        $startDate = now()->addDay();

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDays(15)->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_end_date');

        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDays(14)->toDateString(),
            'insurance_fee' => '0',
        ])->assertCreated()
            ->assertJsonPath('data.total_days', 14);
    }

    public function test_rental_updates_cannot_exceed_fourteen_days(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-UPDATE-LIMIT',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-UPDATE-LIMIT',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->addDays(20)->toDateString(),
            'rental_end_date' => now()->addDays(21)->toDateString(),
            'total_days' => 1,
            'daily_rate' => 80.25,
            'insurance_fee' => 0,
            'total_rental_cost' => 80.25,
        ]);
        $startDate = now()->addDay();

        $this->putJson("/rentals/{$rental->id}", [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDays(15)->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_end_date');

        $this->putJson("/rentals/{$rental->id}", [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDays(14)->toDateString(),
            'insurance_fee' => '0',
        ])->assertOk()
            ->assertJsonPath('data.total_days', 14);
    }

    public function test_rental_updates_reject_changed_dates_in_the_past(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-UPDATE',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-UPDATE',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'daily_rate' => 80.25,
            'insurance_fee' => 0,
            'total_rental_cost' => 80.25,
        ]);

        $this->putJson("/rentals/{$rental->id}", [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->subDay()->toDateString(),
            'rental_end_date' => now()->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_start_date');
    }

    public function test_vehicle_customer_and_rental_creation_forms_are_available(): void
    {
        $this->get('/fleet-cars/create')->assertOk();
        $this->get('/customers/create')->assertOk();
        $this->get('/rentals/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rentals/create')
                ->has('fleetCars')
                ->has('carModels'));
    }
}
