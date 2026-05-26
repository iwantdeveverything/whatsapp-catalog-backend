<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'status' => 'required|string|in:active,draft,archived',
            'images' => 'nullable|array',
            'images.*' => 'url',
            'variant_options' => 'nullable|array',
            'item_group_id' => 'nullable|exists:products,id',
        ];
    }
}
