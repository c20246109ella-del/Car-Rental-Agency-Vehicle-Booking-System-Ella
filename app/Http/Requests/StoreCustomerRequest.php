<?php

namespace App\Http\Requests;

use App\Services\CarRentalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('driver_license'))) {
            $this->merge([
                'driver_license' => CarRentalService::normalizeDriverLicense($this->input('driver_license')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'driver_license' => [
                'required',
                'string',
                'max:13',
                Rule::unique('customers', 'driver_license'),
            ],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:11'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_license.max' => 'The driver license must not exceed 13 characters.',
            'customer_phone.max' => 'The phone number must not exceed 11 characters.',
        ];
    }
}
