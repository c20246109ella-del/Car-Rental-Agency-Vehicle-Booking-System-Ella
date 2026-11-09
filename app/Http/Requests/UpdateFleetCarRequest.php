<?php

namespace App\Http\Requests;

use App\Services\CarRentalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFleetCarRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'vehicle_vin' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fleet_cars', 'vehicle_vin')->ignore($id),
            ],

            'car_model_id' => [
                'required',
                'integer',
                'exists:car_models,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_vin.required' => 'The vehicle VIN is required.',
            'vehicle_vin.unique' => 'This VIN belongs to another vehicle.',
            'car_model_id.required' => 'Please select a car model.',
            'car_model_id.exists' => 'The selected car model does not exist.',
        ];
    }
}