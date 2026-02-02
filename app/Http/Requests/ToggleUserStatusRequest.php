<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle user active status request validation.
 */
class ToggleUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only managers and admins can toggle user status
        return $this->user()?->hasAnyRole(['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Active status is required.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
