<?php

namespace App\Models;

use Database\Factories\ProductFactory;
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
}
