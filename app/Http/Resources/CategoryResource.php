<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the category into the frontend contract shape (CAT-03).
     *
     * Emits exactly FIVE camelCase keys per API_CONTRACT §3.1: `id` (the slug,
     * not the integer PK), `name`, `slug`, `description`, and `productCount`
     * (from the `withCount('products')` aggregate, `products_count`). The
     * `is_active` column is internal and intentionally NOT exposed — it belongs
     * to Products in the frontend contract, not Categories. No timestamps,
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
            'productCount' => (int) ($this->products_count ?? 0),
        ];
    }
}
