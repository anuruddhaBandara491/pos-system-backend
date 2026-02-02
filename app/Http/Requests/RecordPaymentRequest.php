<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'method' => 'required|string|in:cash,card,check,mobile,other',
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'method.in' => 'Payment method must be one of: cash, card, check, mobile, other.',
            'amount.decimal' => 'Amount must have at most 2 decimal places.',
            'amount.min' => 'Payment amount must be greater than 0.',
        ];
    }
}
