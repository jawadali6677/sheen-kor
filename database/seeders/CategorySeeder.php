<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Environment',
                'description' => 'Stories and articles about environmental issues and awareness.',
            ],
            [
                'name' => 'Nature',
                'description' => 'Explore nature, landscapes, mountains, rivers and beautiful places.',
            ],
            [
                'name' => 'Wildlife',
                'description' => 'Wildlife, animals and biodiversity stories.',
            ],
            [
                'name' => 'Forests',
                'description' => 'Forest conservation, plantation and forestry stories.',
            ],
            [
                'name' => 'Climate',
                'description' => 'Climate change, weather and environmental impact.',
            ],
            [
                'name' => 'Tourism',
                'description' => 'Responsible tourism and beautiful destinations.',
            ],
            [
                'name' => 'Agriculture',
                'description' => 'Agriculture, farming and sustainable practices.',
            ],
            [
                'name' => 'Conservation',
                'description' => 'Conservation projects and environmental protection.',
            ],
            [
                'name' => 'Community',
                'description' => 'Community activities, volunteer work and local initiatives.',
            ],
            [
                'name' => 'Tips',
                'description' => 'Practical advice for cleaner, greener everyday life.',
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'status' => true,
            ]);
        }
    }
}
