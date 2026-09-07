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
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}

if (! function_exists('shortVideoRules')) {
    /**
     * @return array<string, list<mixed>>
     */
    function shortVideoRules(string $field = 'videos'): array
    {
        return [
            $field => ['nullable', 'array', 'max:3'],
            $field.'.*' => [
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:20480',
            ],
        ];
    }
}
