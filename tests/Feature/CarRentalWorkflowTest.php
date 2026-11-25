<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\FleetCar;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CarRentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_token_endpoint_returns_a_session_token(): void
    {
        $this->getJson('/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['csrf_token']);
    }

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

    public function test_customer_license_and_phone_lengths_are_limited(): void
    {
        $this->postJson('/customers', [
            'driver_license' => 'DL12345678901',
            'customer_name' => 'Length Limit Customer',
            'customer_phone' => '12345678901',
        ])->assertCreated();

        $this->postJson('/customers', [
            'driver_license' => 'DL123456789012',
            'customer_name' => 'Long License',
            'customer_phone' => '12345678901',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('driver_license');

        $this->postJson('/customers', [
            'driver_license' => 'DL12345678902',
            'customer_name' => 'Long Phone',
            'customer_phone' => '123456789012',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_phone');
    }

    public function test_fleet_cars_are_created_with_sanitized_uppercase_vins(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => '  <b>1hgcm82633a004352</b> ',
            'car_model_id' => $carModel->id,
        ])->assertCreated()
            ->assertJsonPath('data.vehicle_vin', '1HGCM82633A004352');

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => ' 1hgcm82633a004352 ',
            'car_model_id' => $carModel->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_vin');

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => 'SHORTVIN123',
            'car_model_id' => $carModel->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_vin');

        $this->postJson('/fleet-cars', [
            'vehicle_vin' => '1HGCM82633A00435299',
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
            'vehicle_vin' => ' 1hgcm82633a004353 ',
            'car_model_id' => $updatedModel->id,
        ])->assertOk()
            ->assertJsonPath('data.vehicle_vin', '1HGCM82633A004353')
            ->assertJsonPath('data.car_model_id', $updatedModel->id);

        $this->assertDatabaseHas('fleet_cars', [
            'id' => $fleetCar->id,
            'vehicle_vin' => '1HGCM82633A004353',
            'car_model_id' => $updatedModel->id,
        ]);

        $this->putJson("/fleet-cars/{$fleetCar->id}", [
            'vehicle_vin' => 'SHORTVIN123',
            'car_model_id' => $updatedModel->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_vin');
    }

    public function test_fleet_cars_with_rental_history_can_be_archived_and_restored(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-FLEET-DELETE',
            'customer_name' => 'Fleet Delete Customer',
            'customer_phone' => '555-0101',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Delete Test Sedan',
            'daily_rate' => 80.25,
        ]);
        $unusedFleetCar = FleetCar::create([
            'vehicle_vin' => 'UNUSEDVIN12345678',
            'car_model_id' => $carModel->id,
        ]);
        $this->deleteJson("/fleet-cars/{$unusedFleetCar->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Fleet vehicle moved to the archive.');
        $this->assertSoftDeleted('fleet_cars', ['id' => $unusedFleetCar->id]);

        $fleetCarWithRental = FleetCar::create([
            'vehicle_vin' => 'RENTEDVIN12345678',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCarWithRental->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'daily_rate' => 80.25,
            'insurance_fee' => 0,
            'total_rental_cost' => 80.25,
        ]);
        $rental->delete();

        $this->deleteJson("/fleet-cars/{$fleetCarWithRental->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Fleet vehicle moved to the archive.');
        $this->assertSoftDeleted('fleet_cars', ['id' => $fleetCarWithRental->id]);

        $this->postJson("/fleet-cars/{$fleetCarWithRental->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Fleet vehicle restored successfully.');
        $this->assertDatabaseHas('fleet_cars', ['id' => $fleetCarWithRental->id, 'deleted_at' => null]);
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

    public function test_car_models_can_be_listed_archived_and_restored_even_when_in_use(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Compact Sedan',
            'daily_rate' => 69.99,
        ]);

        $this->getJson('/car-models')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $carModel->id,
                'model_name' => 'Compact Sedan',
                'daily_rate' => '69.99',
            ]);

        $this->deleteJson("/car-models/{$carModel->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Car model moved to the archive.');

        $this->assertSoftDeleted('car_models', [
            'id' => $carModel->id,
        ]);

        $carModelInUse = CarModel::create([
            'model_name' => 'SUV',
            'daily_rate' => 120,
        ]);
        FleetCar::create([
            'vehicle_vin' => 'VIN-DELETE-PROTECT',
            'car_model_id' => $carModelInUse->id,
        ]);

        $this->deleteJson("/car-models/{$carModelInUse->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Car model moved to the archive.');
        $this->assertSoftDeleted('car_models', ['id' => $carModelInUse->id]);

        $this->postJson("/car-models/{$carModelInUse->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Car model restored successfully.');
        $this->assertDatabaseHas('car_models', ['id' => $carModelInUse->id, 'deleted_at' => null]);
    }

    public function test_archived_inventory_is_shown_in_archive_and_cannot_be_rented(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-ARCHIVED-INVENTORY',
            'customer_name' => 'Archived Inventory Customer',
            'customer_phone' => '555-0101',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Archived Inventory Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-ARCHIVED-INVT',
            'car_model_id' => $carModel->id,
        ]);

        $this->deleteJson("/car-models/{$carModel->id}")->assertOk();
        $this->getJson('/rentals/create')
            ->assertOk()
            ->assertJsonPath('car_models', [])
            ->assertJsonPath('fleet_cars', []);
        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => today()->toDateString(),
            'rental_end_date' => today()->addDay()->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fleet_car_id');

        $this->deleteJson("/fleet-cars/{$fleetCar->id}")->assertOk();
        $this->get('/rentals/archive')
            ->assertInertia(fn (Assert $page) => $page
                ->component('rentals/archive')
                ->has('fleetCars.data', 1)
                ->where('fleetCars.data.0.vehicleVin', 'VIN-ARCHIVED-INVT')
                ->has('carModels.data', 1)
                ->where('carModels.data.0.modelName', 'Archived Inventory Sedan'));

        $this->postJson("/fleet-cars/{$fleetCar->id}/restore")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Restore the car model before restoring this fleet vehicle.');

        $this->postJson("/car-models/{$carModel->id}/restore")->assertOk();
        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => today()->toDateString(),
            'rental_end_date' => today()->addDay()->toDateString(),
            'insurance_fee' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fleet_car_id');

        $this->postJson("/fleet-cars/{$fleetCar->id}/restore")->assertOk();

        $this->getJson('/rentals/create')
            ->assertOk()
            ->assertJsonCount(1, 'fleet_cars');
        $this->postJson('/rentals', [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => today()->toDateString(),
            'rental_end_date' => today()->addDay()->toDateString(),
            'insurance_fee' => '0',
        ])->assertCreated();
    }

    public function test_rentals_can_be_deleted_with_their_payment_records(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-DELETE',
            'customer_name' => 'Taylor Driver',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-DELETE',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => '2026-12-01',
            'rental_end_date' => '2026-12-04',
            'total_days' => 3,
            'daily_rate' => 80.25,
            'insurance_fee' => 0,
            'total_rental_cost' => 240.75,
        ]);
        $payment = RentalPayment::create([
            'rental_id' => $rental->id,
            'amount' => 240.75,
            'payment_date' => '2026-12-01',
            'payment_method' => 'Cash',
        ]);

        $this->deleteJson("/rentals/{$rental->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Rental deleted successfully.')
            ->assertJsonPath('data.id', $rental->id);

        $this->assertSoftDeleted('rentals', ['id' => $rental->id]);
        $this->assertDatabaseHas('rental_payments', ['id' => $payment->id]);
    }

    public function test_archived_rentals_can_be_viewed_and_restored(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-ARCHIVE-TEST',
            'customer_name' => 'Archive Test Customer',
            'customer_phone' => '555-0101',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Archive Test Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-ARCHIVE-TEST',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'daily_rate' => 80.25,
            'insurance_fee' => 5,
            'total_rental_cost' => 85.25,
        ]);
        $rental->delete();

        $archiveResponse = $this->get('/rentals/archive');
        $archiveResponse->assertOk();
        $archiveResponse->assertInertia(fn (Assert $page) => $page
                ->component('rentals/archive')
                ->has('rentals.data', 1)
                ->where('rentals.data.0.id', $rental->id));

        $this->postJson("/rentals/{$rental->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Rental restored successfully.');
        $this->assertDatabaseHas('rentals', ['id' => $rental->id, 'deleted_at' => null]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('recentRentals', 1)
                ->where('recentRentals.0.id', $rental->id));

    }

    public function test_rental_index_eager_loads_only_fields_used_by_rental_details(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-RENTAL-INDEX',
            'customer_name' => 'Rental Index Customer',
            'customer_phone' => '555-0101',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Rental Index Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-RENTAL-INDEX',
            'car_model_id' => $carModel->id,
        ]);
        $rental = Rental::create([
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'daily_rate' => 80.25,
            'insurance_fee' => 5,
            'total_rental_cost' => 85.25,
        ]);
        $paymentDate = now()->toDateString();
        $payment = RentalPayment::create([
            'rental_id' => $rental->id,
            'amount' => 85.25,
            'payment_date' => $paymentDate,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
        ]);

        $this->getJson('/rentals')
            ->assertOk()
            ->assertJsonPath('data.0.id', $rental->id)
            ->assertJsonPath('data.0.customer', [
                'id' => $customer->id,
                'driver_license' => 'DL-RENTAL-INDEX',
                'customer_name' => 'Rental Index Customer',
                'customer_phone' => '555-0101',
            ])
            ->assertJsonPath('data.0.fleet_car', [
                'id' => $fleetCar->id,
                'vehicle_vin' => 'VIN-RENTAL-INDEX',
                'car_model_id' => $carModel->id,
                'car_model' => [
                    'id' => $carModel->id,
                    'model_name' => 'Rental Index Sedan',
                    'daily_rate' => '80.25',
                ],
            ])
            ->assertJsonPath('data.0.payments.0', [
                'id' => $payment->id,
                'rental_id' => $rental->id,
                'amount' => '85.25',
                'payment_date' => $paymentDate.'T00:00:00.000000Z',
                'payment_method' => 'Cash',
                'payment_status' => 'Paid',
            ]);
    }

    public function test_rental_index_redirects_browser_visitors_to_the_create_page(): void
    {
        $this->get('/rentals')
            ->assertRedirect('/rentals/create');
    }

    public function test_fleet_car_creation_page_lists_existing_vehicles_for_editing(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-123',
            'car_model_id' => $carModel->id,
        ]);

        $this->get('/fleet-cars/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('fleet-cars/create')
                ->has('fleetCars', 1)
                ->where('fleetCars.0.vehicleVin', 'VIN-123')
                ->where('fleetCars.0.carModelId', $carModel->id)
                ->where('carModels.0.id', $carModel->id)
                ->where('carModels.0.model_name', 'Sedan')
                ->where('carModels.0.daily_rate', '80.25'));

        $this->getJson('/fleet-cars/create')
            ->assertOk()
            ->assertJsonPath('car_models.0', [
                'id' => $carModel->id,
                'model_name' => 'Sedan',
                'daily_rate' => '80.25',
            ])
            ->assertJsonPath('fleet_cars.0', [
                'id' => $fleetCar->id,
                'vehicleVin' => 'VIN-123',
                'carModel' => 'Sedan',
                'carModelId' => $carModel->id,
            ]);
    }

    public function test_fleet_car_index_eager_loads_only_required_car_model_fields(): void
    {
        $carModel = CarModel::create([
            'model_name' => 'Fleet Index Sedan',
            'daily_rate' => 80.25,
        ]);
        FleetCar::create([
            'vehicle_vin' => 'VIN-INDEX',
            'car_model_id' => $carModel->id,
        ]);

        $this->getJson('/fleet-cars')
            ->assertOk()
            ->assertJsonPath('data.0.car_model', [
                'id' => $carModel->id,
                'model_name' => 'Fleet Index Sedan',
                'daily_rate' => '80.25',
            ]);
    }

    public function test_fleet_car_index_redirects_browser_visitors_to_the_create_page(): void
    {
        $this->get('/fleet-cars')
            ->assertRedirect('/fleet-cars/create');
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

    public function test_rental_dates_with_more_than_four_year_digits_are_rejected(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-DATE-YEAR-LIMIT',
            'customer_name' => 'Date Year Limit Customer',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Date Year Limit Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-DATE-YEAR-LIMIT',
            'car_model_id' => $carModel->id,
        ]);
        $startDate = now()->addDay();
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
        $validRentalData = [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDay()->toDateString(),
            'insurance_fee' => '0',
        ];

        $this->postJson('/rentals', [
            ...$validRentalData,
            'rental_start_date' => '123456-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_start_date');

        $this->postJson('/rentals', [
            ...$validRentalData,
            'rental_end_date' => '123456-01-02',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_end_date');

        $this->putJson("/rentals/{$rental->id}", [
            ...$validRentalData,
            'rental_start_date' => '123456-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rental_start_date');

        $this->putJson("/rentals/{$rental->id}", [
            ...$validRentalData,
            'rental_end_date' => '123456-01-02',
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

    public function test_rental_insurance_fee_is_limited_to_one_hundred_thousand_and_two_decimal_places(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-INSURANCE-LIMIT',
            'customer_name' => 'Insurance Limit Customer',
            'customer_phone' => '555-0100',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Insurance Limit Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-INSURANCE-LIMIT',
            'car_model_id' => $carModel->id,
        ]);
        $startDate = now()->addDay();
        $rentalData = [
            'customer_id' => $customer->id,
            'fleet_car_id' => $fleetCar->id,
            'rental_start_date' => $startDate->toDateString(),
            'rental_end_date' => $startDate->copy()->addDay()->toDateString(),
        ];

        $response = $this->postJson('/rentals', [
            ...$rentalData,
            'insurance_fee' => '100000.00',
        ])->assertCreated();

        $rentalId = $response->json('data.id');

        $this->postJson('/rentals', [
            ...$rentalData,
            'insurance_fee' => '100000.01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_fee');

        $this->postJson('/rentals', [
            ...$rentalData,
            'insurance_fee' => '0000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_fee');

        $this->postJson('/rentals', [
            ...$rentalData,
            'insurance_fee' => '12.345',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_fee');

        $updateData = [
            ...$rentalData,
            'insurance_fee' => '100000.01',
        ];
        $this->putJson("/rentals/{$rentalId}", $updateData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_fee');

        $updateData['insurance_fee'] = '12.345';
        $this->putJson("/rentals/{$rentalId}", $updateData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_fee');
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
                ->where('today', today()->toDateString())
                ->has('fleetCars')
                ->has('carModels'));
    }

    public function test_rental_creation_data_selects_only_fields_needed_by_the_form(): void
    {
        $customer = Customer::create([
            'driver_license' => 'DL-RENTAL-FORM',
            'customer_name' => 'Rental Form Customer',
            'customer_phone' => '555-0123',
        ]);
        $carModel = CarModel::create([
            'model_name' => 'Rental Form Sedan',
            'daily_rate' => 80.25,
        ]);
        $fleetCar = FleetCar::create([
            'vehicle_vin' => 'VIN-RENTAL-FORM',
            'car_model_id' => $carModel->id,
        ]);

        $this->getJson('/rentals/create')
            ->assertOk()
            ->assertJsonPath('customers.0', [
                'id' => $customer->id,
                'customer_name' => 'Rental Form Customer',
                'driver_license' => 'DL-RENTAL-FORM',
                'customer_phone' => '555-0123',
            ])
            ->assertJsonPath('car_models.0', [
                'id' => $carModel->id,
                'model_name' => 'Rental Form Sedan',
                'daily_rate' => '80.25',
            ])
            ->assertJsonPath('fleet_cars.0', [
                'id' => $fleetCar->id,
                'vehicle_vin' => 'VIN-RENTAL-FORM',
                'car_model_id' => $carModel->id,
                'car_model' => [
                    'id' => $carModel->id,
                    'model_name' => 'Rental Form Sedan',
                    'daily_rate' => '80.25',
                ],
            ]);
    }
}
