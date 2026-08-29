<?php

use Illuminate\Support\Str;

if (! function_exists('generateUniqueSlug')) {

    function generateUniqueSlug($model, string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);

        $originalSlug = $slug;
        $counter = 1;

        while (
            $model::where('slug', $slug)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}