<?php

use App\Actions\PlaceFeedAds;
use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\MonetizationSetting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

if (! function_exists('stripe_checkout_is_configured')) {
    function stripe_checkout_is_configured(): bool
    {
        return filled(config('cashier.key')) && filled(config('cashier.secret'));
    }
}

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
     * Enabled first-party ads as card arrays. Prefer feed_ads_for() / sidebarCards() for placement rules.
     *
     * @return list<array<string, mixed>>
     */
    function demo_ads(): array
    {
        return Advertisement::query()
            ->enabled()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Advertisement $advertisement): array => $advertisement->toCard(AdPlacement::FeedPosts->value))
            ->all();
    }
}

if (! function_exists('feed_ads_for')) {
    /**
     * @param  LengthAwarePaginator  $paginator
     * @return array<int, array<string, mixed>>
     */
    function feed_ads_for($paginator, string $surface): array
    {
        $placement = AdPlacement::tryFrom($surface);

        if ($placement === null) {
            return [];
        }

        return app(PlaceFeedAds::class)->handle($paginator, $placement);
    }
}

if (! function_exists('monetization_setting')) {
    function monetization_setting(string $key, mixed $default = null): mixed
    {
        $value = MonetizationSetting::query()
            ->where('key', $key)
            ->value('value');

        if ($value === null) {
            return $default;
        }

        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($default)) {
            return (int) $value;
        }

        return $value;
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
