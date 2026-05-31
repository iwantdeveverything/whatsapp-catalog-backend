<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the user into the frontend contract shape.
     *
     * Per spec AUTH-02, the only field exposed for v1 is `email`.
     * No timestamps, no integer id, no name — those are internal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
