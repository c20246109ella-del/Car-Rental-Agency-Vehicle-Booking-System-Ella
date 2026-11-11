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

    public function index(): JsonResponse
    {
        $rentals = Rental::with([
            'customer',
            'fleetCar.carModel',
            'payments',
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
        $customers = Customer::orderBy('customer_name')->get();
        $carModels = CarModel::orderBy('model_name')->get(['id', 'model_name', 'daily_rate']);
        $fleetCars = FleetCar::with('carModel')
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
}