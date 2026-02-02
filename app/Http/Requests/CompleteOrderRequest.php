<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['cashier', 'manager', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tax_rate' => 'nullable|numeric|min:0|max:1|decimal:0,4', // percentage as decimal (0.1 = 10%)
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'tax_rate.decimal' => 'Tax rate must have at most 4 decimal places.',
        ];
    }
}
