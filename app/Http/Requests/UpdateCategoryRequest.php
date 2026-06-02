<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /**
     * Partial update (CAT-05): every field is optional. Per API_CONTRACT §3.4
     * the client may send only `name` and `description`; `is_active` is internal
     * and not accepted. When `name` is present and changed, the controller
     * regenerates the collision-suffixed slug.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
