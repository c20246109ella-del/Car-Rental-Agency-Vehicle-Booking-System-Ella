<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFleetCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_vin' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fleet_cars', 'vehicle_vin'),
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
            'vehicle_vin.unique' => 'This vehicle VIN is already registered.',
            'vehicle_vin.max' => 'The VIN must not exceed 50 characters.',
            'car_model_id.required' => 'Please select a car model.',
            'car_model_id.exists' => 'The selected car model does not exist.',
        ];
    }
}