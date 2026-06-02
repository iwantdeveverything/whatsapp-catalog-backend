<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the category into the frontend contract shape (CAT-03).
     *
     * Emits exactly six camelCase keys. `id` is the slug (not the integer PK),
     * `isActive` maps from `is_active`, and `productCount` comes from the
     * `withCount('products')` aggregate (`products_count`). No timestamps,
     * `deleted_at`, or internal foreign keys leak (INF-05).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'isActive' => (bool) $this->is_active,
            'productCount' => (int) ($this->products_count ?? 0),
        ];
    }
}
