<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFleetCarRequest;
use App\Http\Requests\UpdateFleetCarRequest;
use App\Models\CarModel;
use App\Models\FleetCar;
use App\Services\CarRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FleetCarController extends Controller
{
    protected CarRentalService $carRentalService;

    public function __construct(CarRentalService $carRentalService)
    {
        $this->carRentalService = $carRentalService;
    }

    /*
    |--------------------------------------------------------------------------
    | Display fleet cars
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $fleetCars = FleetCar::with('carModel')
            ->latest()
            ->paginate(10);

        return response()->json($fleetCars);
    }

    /*
    |--------------------------------------------------------------------------
    | Return data required for the creation form
    |--------------------------------------------------------------------------
    */

    public function create(Request $request): JsonResponse|Response
    {
        $carModels = CarModel::orderBy('model_name')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'car_models' => $carModels,
            ]);
        }

        return Inertia::render('fleet-cars/create', [
            'carModels' => $carModels,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store a fleet car
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreFleetCarRequest $request
    ): JsonResponse {
        $fleetCar = $this->carRentalService->createFleetCar(
            $request->validated()
        );

        return response()->json([
            'message' => 'Fleet car created successfully.',
            'data' => $fleetCar->load('carModel'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update a fleet car
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateFleetCarRequest $request,
        $id
    ): JsonResponse {
        $fleetCar = $this->carRentalService->updateFleetCar(
            (int) $id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Fleet car updated successfully.',
            'data' => $fleetCar,
        ]);
    }
}