<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarModelRequest;
use App\Http\Requests\UpdateCarModelRequest;
use App\Models\CarModel;
use App\Services\CarRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () use ($id): void {
            $carModel = CarModel::query()->lockForUpdate()->findOrFail($id);
            $carModel->delete();
        });

        return response()->json([
            'message' => 'Car model moved to the archive.',
            'data' => ['id' => $id],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        DB::transaction(function () use ($id): void {
            $carModel = CarModel::onlyTrashed()->lockForUpdate()->findOrFail($id);
            $carModel->restore();
        });

        return response()->json([
            'message' => 'Car model restored successfully.',
            'data' => ['id' => $id],
        ]);
    }
}
