<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the product into the frontend contract shape (PROD-04).
     *
     * Emits EXACTLY nine top-level keys per API_CONTRACT / ProductSchema:
     * `id` (the slug, not the integer PK), `name`, `description`, `price`
     * (decimal cast to float, or null), `currency`, `category` (the category
     * NAME, not its id), `images`, `isActive` (true only when `is_active`
     * AND not soft-deleted), and a nested `contact` object. Internal columns
     * (`category_id`, `deleted_at`, timestamps, `item_group_id`, `slug`,
     * `variant_options`) intentionally never leak (INF-05).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price === null ? null : (float) $this->price,
            'currency' => $this->currency,
            'category' => $this->category?->name,
            'images' => $this->images === null ? [] : $this->images->toArray(),
            'isActive' => $this->is_active === true && $this->deleted_at === null,
            'contact' => [
                'whatsapp' => $this->whatsapp,
                'phone' => $this->phone,
            ],
        ];
    }
}
