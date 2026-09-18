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
        Schema::create('listing_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('monetization_packages')->nullOnDelete();
            $table->string('status');
            $table->string('source');
            $table->string('placement');
            $table->string('package_type');
            $table->string('package_name');
            $table->string('package_slug');
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['market_listing_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index(['placement', 'status']);
            $table->index(['user_id', 'status']);
        });

        $now = now();

        $packages = [
            [
                'type' => MonetizationPackageType::ListingPromotion->value,
                'name' => 'Featured Listing - 7 Days',
                'slug' => 'listing_featured_7d',
                'duration_days' => 7,
                'placement' => 'featured_home',
                'sort_order' => 50,
            ],
            [
                'type' => MonetizationPackageType::ListingPromotion->value,
                'name' => 'Top of Category - 7 Days',
                'slug' => 'listing_category_7d',
                'duration_days' => 7,
                'placement' => 'top_of_category',
                'sort_order' => 60,
            ],
            [
                'type' => MonetizationPackageType::ListingPromotion->value,
                'name' => 'Listing Promotion - 7 Days',
                'slug' => 'listing_boost_7d',
                'duration_days' => 7,
                'placement' => 'boost_rank',
                'sort_order' => 70,
            ],
        ];

        foreach ($packages as $package) {
            DB::table('monetization_packages')->insert([
                ...$package,
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
        Schema::dropIfExists('listing_promotions');

        DB::table('monetization_packages')
            ->whereIn('slug', ['listing_featured_7d', 'listing_category_7d', 'listing_boost_7d'])
            ->delete();
    }
};
