<?php

namespace App\Http\Requests;

use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rental = Rental::find($this->route('id'));
        $currentStartDate = $rental?->rental_start_date?->toDateString();
        $currentEndDate = $rental?->rental_end_date?->toDateString();

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
                function ($attribute, $value, $fail) use ($currentStartDate) {
                    if (is_string($value) && $value < today()->toDateString() && $value !== $currentStartDate) {
                        $fail('The rental start date cannot be in the past.');
                    }
                },
            ],

            'rental_end_date' => [
                'required',
                'date',
                'after:rental_start_date',
                function ($attribute, $value, $fail) use ($currentEndDate) {
                    if (is_string($value) && $value < today()->toDateString() && $value !== $currentEndDate) {
                        $fail('The rental end date cannot be in the past.');
                    }
                },
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