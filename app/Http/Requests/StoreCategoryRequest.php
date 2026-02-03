<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['manager', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The category name is required.',
            'name.unique' => 'A category with this name already exists.',
            'name.max' => 'The category name must not exceed 255 characters.',
        ];
    }
}
