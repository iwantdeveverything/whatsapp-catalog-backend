<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|max:3',
            'status' => 'sometimes|required|string|in:active,draft,archived',
            'images' => 'nullable|array',
            'images.*' => 'url',
            'variant_options' => 'nullable|array',
            'item_group_id' => 'nullable|exists:products,id',
        ];
    }
}
