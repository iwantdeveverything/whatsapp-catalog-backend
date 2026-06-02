<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * Per API_CONTRACT §3.3 (CategoryFormSchema) the client may send only
     * `name` and `description`. Slug is auto-generated from `name` server-side
     * (CAT-04) and `is_active` is internal (DB default `true`); neither is
     * accepted from the client.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }
}
