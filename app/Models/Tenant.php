<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'status'];

    /**
     * Only active (and, via SoftDeletes' default scope, non-trashed) tenants.
     * Used by tenant resolution to reject suspended or soft-deleted tenants.
     *
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
