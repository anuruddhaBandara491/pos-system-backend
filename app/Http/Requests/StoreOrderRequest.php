<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'branch_id' => 'required|integer|exists:branches,id',
            'discount' => 'nullable|numeric|min:0|decimal:0,2',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'branch_id.exists' => 'The selected branch does not exist.',
            'discount.decimal' => 'Discount must have at most 2 decimal places.',
        ];
    }
}
