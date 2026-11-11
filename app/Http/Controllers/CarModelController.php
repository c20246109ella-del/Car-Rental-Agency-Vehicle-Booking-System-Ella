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
}
