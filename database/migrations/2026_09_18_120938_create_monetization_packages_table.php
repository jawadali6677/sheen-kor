<?php

use App\Enums\MonetizationPackageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monetization_packages', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('placement')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_enabled']);
        });

        $now = now();

        $packages = [
            [
                'type' => MonetizationPackageType::GreenTick->value,
                'name' => 'Green Tick Monthly',
                'slug' => 'green_tick_monthly',
                'duration_days' => 30,
                'sort_order' => 10,
            ],
            [
                'type' => MonetizationPackageType::GreenTick->value,
                'name' => 'Green Tick Yearly',
                'slug' => 'green_tick_yearly',
                'duration_days' => 365,
                'sort_order' => 20,
            ],
            [
                'type' => MonetizationPackageType::PostBoost->value,
                'name' => 'Post Boost - 1 Day',
                'slug' => 'post_boost_1d',
                'duration_days' => 1,
                'sort_order' => 30,
            ],
            [
                'type' => MonetizationPackageType::PostBoost->value,
                'name' => 'Post Boost - 7 Days',
                'slug' => 'post_boost_7d',
                'duration_days' => 7,
                'sort_order' => 40,
            ],
        ];

        foreach ($packages as $package) {
            DB::table('monetization_packages')->insert([
                ...$package,
                'placement' => null,
                'price' => '0.00',
                'currency' => 'USD',
                'is_enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monetization_packages');
    }
};
