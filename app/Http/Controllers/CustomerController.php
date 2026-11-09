<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Services\CarRentalService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('customers/create');
    }

    public function store(
        StoreCustomerRequest $request,
        CarRentalService $carRentalService
    ): JsonResponse {
        $customer = $carRentalService->createCustomer($request->validated());

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }
}
