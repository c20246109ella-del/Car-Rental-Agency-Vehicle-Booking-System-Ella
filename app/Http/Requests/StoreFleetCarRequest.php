<?php

namespace App\Http\Requests;

use App\Services\CarRentalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFleetCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('vehicle_vin'))) {
            $this->merge([
                'vehicle_vin' => CarRentalService::normalizeVehicleVin($this->input('vehicle_vin')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'vehicle_vin' => [
                'required',
                'string',
                'size:17',
                Rule::unique('fleet_cars', 'vehicle_vin'),
            ],

            'car_model_id' => [
                'required',
                'integer',
                Rule::exists('car_models', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_vin.required' => 'The vehicle VIN is required.',
            'vehicle_vin.unique' => 'This vehicle VIN is already registered.',
            'vehicle_vin.size' => 'The VIN must be exactly 17 characters.',
            'car_model_id.required' => 'Please select a car model.',
            'car_model_id.exists' => 'The selected car model does not exist.',
        ];
    }
}