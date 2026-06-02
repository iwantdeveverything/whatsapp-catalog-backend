<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * Generate a URL-safe, collision-free slug for the given table.
     *
     * Normalizes the name with Laravel's Str::slug (lowercase, accent strip,
     * non-alphanumeric to hyphen, trimmed). If the base slug already exists in
     * the table's `slug` column, a numeric suffix is appended (`-2`, `-3`, ...)
     * until a free slug is found. The optional `$ignoreId` excludes the row
     * currently being updated so renaming back to an unchanged slug is allowed.
     *
     * Generic across domains (Category now, Product later).
     */
    public function generate(string $name, string $table, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($this->exists($slug, $table, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function exists(string $slug, string $table, ?int $ignoreId): bool
    {
        $query = DB::table($table)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
