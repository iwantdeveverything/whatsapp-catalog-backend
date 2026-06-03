<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'whatsapp',
        'phone',
        'is_active',
        'images',
        'variant_options',
        'item_group_id',
    ];

    protected function casts(): array
    {
        return [
            'images' => AsArrayObject::class,
            'variant_options' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Product::class, 'item_group_id');
    }

    public function masterProduct()
    {
        return $this->belongsTo(Product::class, 'item_group_id');
    }

    /**
     * Only active, non-trashed products (PROD-01). SoftDeletes already
     * excludes trashed rows from the default query.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Case-insensitive partial match against `name` (PROD-02). A null or
     * empty term is a no-op so callers can pass the raw query param through.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        return $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }

    /**
     * Order by an allow-listed field and direction (PROD-02). Validation of
     * the allowed values happens upstream in IndexProductRequest.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOrdered(Builder $query, string $field, string $order): Builder
    {
        return $query->orderBy($field, $order);
    }
}
