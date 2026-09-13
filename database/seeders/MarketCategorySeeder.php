<?php

namespace Database\Seeders;

use App\Models\MarketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketCategorySeeder extends Seeder
{
    /**
     * @var list<array{name: string, description: string}>
     */
    private array $categories = [
        [
            'name' => 'Vehicles',
            'description' => 'Cars, bikes, and other vehicles looking for a second life.',
        ],
        [
            'name' => 'Electronics',
            'description' => 'TVs, cameras, audio, and other working electronics.',
        ],
        [
            'name' => 'Mobile Phones',
            'description' => 'Phones, tablets, and related accessories.',
        ],
        [
            'name' => 'Computers',
            'description' => 'Laptops, desktops, parts, and computer accessories.',
        ],
        [
            'name' => 'Furniture',
            'description' => 'Tables, chairs, shelves, and other household furniture.',
        ],
        [
            'name' => 'Home & Garden',
            'description' => 'Household, kitchen, and garden items for everyday reuse.',
        ],
        [
            'name' => 'Clothing',
            'description' => 'Clothing and accessories that can be reused.',
        ],
        [
            'name' => 'Books',
            'description' => 'Books, magazines, and educational materials.',
        ],
        [
            'name' => 'Sports',
            'description' => 'Sporting goods, outdoor gear, and fitness equipment.',
        ],
        [
            'name' => 'Jobs & Services',
            'description' => 'Local jobs, skills, and community services.',
        ],
        [
            'name' => 'Other',
            'description' => 'Useful items that do not fit another category.',
        ],
    ];

    public function run(): void
    {
        $slugs = [];

        foreach ($this->categories as $category) {
            $slug = Str::slug($category['name']);
            $slugs[] = $slug;

            MarketCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ],
            );
        }

        MarketCategory::query()
            ->whereNotIn('slug', $slugs)
            ->update(['is_active' => false]);
    }
}
