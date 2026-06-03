<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Query-param contract for the product index (PROD-02). `search` and
     * `category` are free-form optional filters; `sortBy` and `sortOrder`
     * are allow-listed — anything outside the lists yields a 422 before the
     * query is built.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'sortBy' => ['sometimes', Rule::in(['name', 'price'])],
            'sortOrder' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
