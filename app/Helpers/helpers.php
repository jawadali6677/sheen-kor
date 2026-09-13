<?php

use App\Models\User;
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

if (! function_exists('generateUniqueUsername')) {

    function generateUniqueUsername(string $name, ?int $ignoreId = null): string
    {
        $base = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9._]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.')
            ->toString();

        if (strlen($base) < 3) {
            $base = 'user';
        }

        $base = rtrim(substr($base, 0, 30), '._');

        if (strlen($base) < 3) {
            $base = 'user';
        }

        $username = $base;
        $counter = 1;

        while (
            User::query()
                ->where('username', $username)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $suffix = '.'.$counter;
            $username = rtrim(substr($base, 0, 30 - strlen($suffix)), '._').$suffix;
            $counter++;
        }

        return $username;
    }
}

if (! function_exists('demo_ads')) {
    /**
     * Placeholder advertising inventory for UI only.
     *
     * @return list<array{id: string, advertiser: string, title: string, description: string, cta: string, image: string, url: string}>
     */
    function demo_ads(): array
    {
        return [
            [
                'id' => 'demo-bottles',
                'advertiser' => 'GreenPath Supply',
                'title' => 'Refill bottles for every trail',
                'description' => 'Durable bottles made for daily use. A small swap that keeps plastic out of parks.',
                'cta' => 'Learn more',
                'image' => 'https://images.unsplash.com/photo-1523362628745-0c100150b504?auto=format&fit=crop&w=800&q=80',
                'url' => '#',
            ],
            [
                'id' => 'demo-trees',
                'advertiser' => 'Canopy Collective',
                'title' => 'Plant a tree with your next walk',
                'description' => 'Local planting days across the city. Bring gloves, leave with a greener street.',
                'cta' => 'See events',
                'image' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=800&q=80',
                'url' => '#',
            ],
        ];
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
