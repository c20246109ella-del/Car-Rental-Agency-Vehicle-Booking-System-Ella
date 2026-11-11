<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_name' => [
                'required',
                'string',
                'max:100',
            ],
            'daily_rate' => [
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
            'model_name.required' => 'The car model name is required.',
            'model_name.max' => 'The car model name must not exceed 100 characters.',
            'daily_rate.min' => 'The daily rate cannot be negative.',
            'daily_rate.decimal' => 'The daily rate must have at most two decimal places.',
        ];
    }
}
