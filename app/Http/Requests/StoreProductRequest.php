<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'branch_id' => 'required|integer|exists:branches,id',
            'sku' => 'required|string|max:50|unique:products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01|decimal:0,2',
            'cost' => 'required|numeric|min:0|decimal:0,2',
            'stock_qty' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'A product with this SKU already exists.',
            'branch_id.exists' => 'The selected branch does not exist.',
            'price.decimal' => 'Price must have at most 2 decimal places.',
            'cost.decimal' => 'Cost must have at most 2 decimal places.',
        ];
    }
}
