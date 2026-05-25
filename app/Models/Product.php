<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'currency',
        'status',
        'images',
        'variant_options',
        'item_group_id',
    ];

    protected function casts(): array
    {
        return [
            'images' => AsArrayObject::class,
            'variant_options' => 'array',
        ];
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
