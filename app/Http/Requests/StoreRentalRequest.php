<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'fleet_car_id' => [
                'required',
                'integer',
                'exists:fleet_cars,id',
            ],

            'rental_start_date' => [
                'required',
                'date',
            ],

            'rental_end_date' => [
                'required',
                'date',
                'after:rental_start_date',
            ],

            'insurance_fee' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'fleet_car_id.required' => 'Please select a vehicle.',
            'fleet_car_id.exists' => 'The selected vehicle does not exist.',
            'rental_start_date.required' => 'The rental start date is required.',
            'rental_end_date.after' => 'The end date must be after the start date.',
            'insurance_fee.min' => 'The insurance fee cannot be negative.',
            'insurance_fee.decimal' => 'The insurance fee must have at most two decimal places.',
        ];
    }
}