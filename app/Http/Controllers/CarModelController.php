<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarModelRequest;
use App\Http\Requests\UpdateCarModelRequest;
use App\Models\CarModel;
use App\Services\CarRentalService;
use Illuminate\Http\JsonResponse;

class CarModelController extends Controller
{
    public function __construct(
        private readonly CarRentalService $carRentalService
    ) {}

    public function index(): JsonResponse
    {
        $carModels = CarModel::query()
            ->orderBy('model_name')
            ->get();

        return response()->json($carModels);
    }

    public function store(StoreCarModelRequest $request): JsonResponse
    {
        $carModel = $this->carRentalService->createCarModel(
            $request->validated()
        );

        return response()->json([
            'message' => 'Car model created successfully.',
            'data' => $carModel,
        ], 201);
    }

    public function update(UpdateCarModelRequest $request, int $id): JsonResponse
    {
        $carModel = $this->carRentalService->updateCarModel(
            $id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Car model updated successfully.',
            'data' => $carModel,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $carModel = CarModel::query()->findOrFail($id);

        if ($carModel->fleetCars()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this car model because it is currently assigned to one or more fleet cars.',
            ], 409);
        }

        $carModel->delete();

        return response()->json([
            'message' => 'Car model deleted successfully.',
            'data' => ['id' => $id],
        ]);
    }
}
