<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRentalRequest;
use App\Http\Requests\UpdateRentalRequest;
use App\Models\Customer;
use App\Models\CarModel;
use App\Models\FleetCar;
use App\Models\Rental;
use App\Services\CarRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RentalController extends Controller
{
    protected CarRentalService $carRentalService;

    public function __construct(CarRentalService $carRentalService)
    {
        $this->carRentalService = $carRentalService;
    }

    /*
    |--------------------------------------------------------------------------
    | Display rentals
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('rentals.create');
        }

        $rentals = Rental::query()
            ->select([
                'id',
                'customer_id',
                'fleet_car_id',
                'rental_start_date',
                'rental_end_date',
                'total_days',
                'daily_rate',
                'insurance_fee',
                'total_rental_cost',
                'created_at',
                'updated_at',
            ])
            ->with([
                'customer:id,driver_license,customer_name,customer_phone',
                'fleetCar:id,vehicle_vin,car_model_id',
                'fleetCar.carModel:id,model_name,daily_rate',
                'payments:id,rental_id,amount,payment_date,payment_method,payment_status',
            ])
            ->latest()
            ->paginate(10);

        return response()->json($rentals);
    }

    /*
    |--------------------------------------------------------------------------
    | Return data required for the rental creation form
    |--------------------------------------------------------------------------
    */

    public function create(Request $request): JsonResponse|Response
    {
        $customers = Customer::query()
            ->select(['id', 'customer_name', 'driver_license', 'customer_phone'])
            ->orderBy('customer_name')
            ->get();
        $carModels = CarModel::query()
            ->select(['id', 'model_name', 'daily_rate'])
            ->orderBy('model_name')
            ->get();
        $fleetCars = FleetCar::query()
            ->whereHas('carModel')
            ->select(['id', 'vehicle_vin', 'car_model_id'])
            ->with('carModel:id,model_name,daily_rate')
            ->orderBy('vehicle_vin')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'customers' => $customers,
                'fleet_cars' => $fleetCars,
                'car_models' => $carModels,
            ]);
        }

        return Inertia::render('rentals/create', [
            'customers' => $customers,
            'fleetCars' => $fleetCars,
            'carModels' => $carModels,
            'today' => today()->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store a rental
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreRentalRequest $request
    ): JsonResponse {
        $rental = $this->carRentalService->createRental(
            $request->validated()
        );

        return response()->json([
            'message' => 'Rental created successfully.',
            'data' => $rental->load([
                'customer',
                'fleetCar.carModel',
                'payments',
            ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update a rental
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateRentalRequest $request,
        $id
    ): JsonResponse {
        $rental = $this->carRentalService->updateRental(
            (int) $id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Rental updated successfully.',
            'data' => $rental,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $rental = Rental::query()->findOrFail($id);
        $rental->delete();

        return response()->json([
            'message' => 'Rental deleted successfully.',
            'data' => ['id' => $id],
        ]);
    }

    public function archive(): Response
    {
        $rentals = Rental::onlyTrashed()
            ->select([
                'id',
                'customer_id',
                'fleet_car_id',
                'rental_start_date',
                'rental_end_date',
                'total_days',
                'daily_rate',
                'insurance_fee',
                'total_rental_cost',
                'deleted_at',
            ])
            ->with([
                'customer:id,customer_name,driver_license,customer_phone',
                'fleetCar' => static fn ($query) => $query->withTrashed()->select(['id', 'vehicle_vin', 'car_model_id']),
                'fleetCar.carModel' => static fn ($query) => $query->withTrashed()->select(['id', 'model_name']),
            ])
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'rentals_page');

        $rentals->through(static fn (Rental $rental): array => [
            'id' => $rental->id,
            'customerName' => $rental->customer->customer_name,
            'customerLicense' => $rental->customer->driver_license,
            'vehicleVin' => $rental->fleetCar->vehicle_vin,
            'carModel' => $rental->fleetCar->carModel->model_name,
            'startDate' => $rental->rental_start_date->toDateString(),
            'endDate' => $rental->rental_end_date->toDateString(),
            'totalCost' => $rental->total_rental_cost,
            'deletedAt' => $rental->deleted_at?->toISOString(),
        ]);

        $fleetCars = FleetCar::onlyTrashed()
            ->select(['id', 'vehicle_vin', 'car_model_id', 'deleted_at'])
            ->with(['carModel' => static fn ($query) => $query->withTrashed()->select(['id', 'model_name', 'deleted_at'])])
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'fleet_cars_page')
            ->through(static fn (FleetCar $fleetCar): array => [
                'id' => $fleetCar->id,
                'vehicleVin' => $fleetCar->vehicle_vin,
                'carModel' => $fleetCar->carModel->model_name,
                'carModelArchived' => $fleetCar->carModel->trashed(),
                'deletedAt' => $fleetCar->deleted_at?->toISOString(),
            ]);

        $carModels = CarModel::onlyTrashed()
            ->select(['id', 'model_name', 'daily_rate', 'deleted_at'])
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'car_models_page')
            ->through(static fn (CarModel $carModel): array => [
                'id' => $carModel->id,
                'modelName' => $carModel->model_name,
                'dailyRate' => $carModel->daily_rate,
                'deletedAt' => $carModel->deleted_at?->toISOString(),
            ]);

        return Inertia::render('rentals/archive', [
            'rentals' => $rentals,
            'fleetCars' => $fleetCars,
            'carModels' => $carModels,
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $rental = Rental::onlyTrashed()->findOrFail($id);
        $rental->restore();
        $rental->touch();

        return response()->json([
            'message' => 'Rental restored successfully.',
            'data' => ['id' => $id],
        ]);
    }

}