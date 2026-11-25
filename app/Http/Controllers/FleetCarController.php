<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFleetCarRequest;
use App\Http\Requests\UpdateFleetCarRequest;
use App\Models\CarModel;
use App\Models\FleetCar;
use App\Services\CarRentalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function index(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('fleet-cars.create');
        }

        $fleetCars = FleetCar::query()
            ->whereHas('carModel')
            ->select(['id', 'vehicle_vin', 'car_model_id', 'created_at', 'updated_at'])
            ->with('carModel:id,model_name,daily_rate')
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
        $carModels = CarModel::query()
            ->select(['id', 'model_name', 'daily_rate'])
            ->orderBy('model_name')
            ->get();
        $fleetCars = FleetCar::query()
            ->whereHas('carModel')
            ->select(['id', 'vehicle_vin', 'car_model_id'])
            ->with('carModel:id,model_name')
            ->orderBy('vehicle_vin')
            ->get()
            ->map(static fn (FleetCar $fleetCar): array => [
                'id' => $fleetCar->id,
                'vehicleVin' => $fleetCar->vehicle_vin,
                'carModel' => $fleetCar->carModel->model_name,
                'carModelId' => $fleetCar->car_model_id,
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'car_models' => $carModels,
                'fleet_cars' => $fleetCars,
            ]);
        }

        return Inertia::render('fleet-cars/create', [
            'carModels' => $carModels,
            'fleetCars' => $fleetCars,
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

    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id): JsonResponse {
            $fleetCar = FleetCar::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $fleetCar->delete();

            return response()->json([
                'message' => 'Fleet vehicle moved to the archive.',
                'data' => ['id' => $id],
            ]);
        });
    }

    public function restore(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id): JsonResponse {
            $fleetCar = FleetCar::onlyTrashed()->lockForUpdate()->findOrFail($id);
            $carModel = CarModel::withTrashed()->lockForUpdate()->findOrFail($fleetCar->car_model_id);

            if ($carModel->trashed()) {
                return response()->json([
                    'message' => 'Restore the car model before restoring this fleet vehicle.',
                ], 409);
            }

            $fleetCar->restore();

            return response()->json([
                'message' => 'Fleet vehicle restored successfully.',
                'data' => ['id' => $id],
            ]);
        });
    }
}