<?php

namespace Tests\Feature;

use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Enums\Permission;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\MarketListingImage;
use App\Models\MarketListingReport;
use App\Models\User;
use Database\Seeders\MarketCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketListingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_create_market_listings_but_cannot_moderate_them(): void
    {
        $member = User::factory()->create();

        $this->assertTrue($member->hasPermission(Permission::CreateMarketListings));
        $this->assertFalse($member->hasPermission(Permission::ModerateMarketListings));
    }

    public function test_moderators_can_create_and_moderate_market_listings(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->assertTrue($moderator->hasPermission(Permission::CreateMarketListings));
        $this->assertTrue($moderator->hasPermission(Permission::ModerateMarketListings));
    }

    public function test_admins_have_market_permissions_without_role_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasPermission(Permission::CreateMarketListings));
        $this->assertTrue($admin->hasPermission(Permission::ModerateMarketListings));
    }

    public function test_market_categories_are_seeded(): void
    {
        $this->seed(MarketCategorySeeder::class);

        $this->assertSame(11, MarketCategory::query()->count());
        $this->assertSame(11, MarketCategory::query()->active()->count());
        $this->assertDatabaseHas('market_categories', [
            'name' => 'Vehicles',
            'slug' => 'vehicles',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('market_categories', [
            'name' => 'Mobile Phones',
            'slug' => 'mobile-phones',
        ]);
        $this->assertDatabaseHas('market_categories', [
            'name' => 'Jobs & Services',
            'slug' => 'jobs-services',
        ]);
        $this->assertDatabaseHas('market_categories', [
            'name' => 'Other',
            'slug' => 'other',
        ]);
    }

    public function test_market_category_seeder_is_idempotent_and_keeps_existing_listings(): void
    {
        $legacy = MarketCategory::query()->create([
            'name' => 'Tools',
            'slug' => 'tools',
            'description' => 'Hand tools from the first seed.',
            'is_active' => true,
        ]);
        $listing = MarketListing::factory()->create([
            'market_category_id' => $legacy->id,
        ]);

        $this->seed(MarketCategorySeeder::class);
        $this->seed(MarketCategorySeeder::class);

        $this->assertSame(1, MarketCategory::query()->where('slug', 'electronics')->count());
        $this->assertSame(11, MarketCategory::query()->active()->count());
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertTrue($listing->fresh()->category->is($legacy));
    }

    public function test_listings_belong_to_a_user_and_category(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue($listing->user->is($user));
        $this->assertInstanceOf(MarketCategory::class, $listing->category);
        $this->assertTrue($user->marketListings->contains($listing));
        $this->assertSame(MarketListingType::Sell, $listing->listing_type);
        $this->assertSame(MarketListingStatus::Published, $listing->status);
    }

    public function test_listing_images_stay_image_media_type(): void
    {
        $image = MarketListingImage::factory()->create([
            'media_type' => 'video',
        ]);

        $this->assertSame('image', $image->fresh()->media_type);
        $this->assertFalse($image->fresh()->isVideo());
        $this->assertTrue($image->listing->images->contains($image));
    }

    public function test_listing_reports_are_unique_per_user_and_listing(): void
    {
        $listing = MarketListing::factory()->create();
        $reporter = User::factory()->create();

        MarketListingReport::factory()->create([
            'user_id' => $reporter->id,
            'market_listing_id' => $listing->id,
        ]);

        $this->expectException(QueryException::class);

        MarketListingReport::factory()->create([
            'user_id' => $reporter->id,
            'market_listing_id' => $listing->id,
        ]);
    }

    public function test_published_scope_hides_pending_listings(): void
    {
        MarketListing::factory()->create();
        MarketListing::factory()->pending()->create();

        $this->assertSame(1, MarketListing::query()->published()->count());
    }
}
