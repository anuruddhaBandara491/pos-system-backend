<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Logout request validation.
 * (Minimal validation; mainly for consistency)
 */
class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Only authenticated users
    }

    public function rules(): array
    {
        return [
            // No required fields; logout just revokes token
        ];
    }
}
