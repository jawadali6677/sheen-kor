<?php

namespace Tests\Feature;

use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketListingBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_only_shows_published_listings(): void
    {
        $published = MarketListing::factory()->create([
            'title' => 'Published herb planter',
        ]);
        $pending = MarketListing::factory()->pending()->create([
            'title' => 'Pending herb planter',
        ]);
        $rejected = MarketListing::factory()->rejected()->create([
            'title' => 'Rejected herb planter',
        ]);
        $sold = MarketListing::factory()->sold()->create([
            'title' => 'Sold herb planter',
        ]);

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSee('Published herb planter')
            ->assertDontSee('Pending herb planter')
            ->assertDontSee('Rejected herb planter')
            ->assertDontSee('Sold herb planter');

        $this->get(route('market.show', $published))->assertOk();
        $this->get(route('market.show', $pending))->assertNotFound();
        $this->get(route('market.show', $rejected))->assertNotFound();
        $this->get(route('market.show', $sold))
            ->assertOk()
            ->assertSee('This listing is no longer available.');
    }

    public function test_catalog_search_matches_title_and_description(): void
    {
        MarketListing::factory()->create([
            'title' => 'Cedar bookshelf',
            'description' => 'A sturdy shelf that still has plenty of life.',
        ]);
        MarketListing::factory()->create([
            'title' => 'Kitchen blender',
            'description' => 'Works well for soups and smoothies every morning.',
        ]);

        $this->get(route('market.index', ['q' => 'bookshelf']))
            ->assertOk()
            ->assertSee('Cedar bookshelf')
            ->assertDontSee('Kitchen blender');

        $this->get(route('market.index', ['q' => 'smoothies']))
            ->assertOk()
            ->assertSee('Kitchen blender')
            ->assertDontSee('Cedar bookshelf');
    }

    public function test_catalog_filters_by_category_type_and_condition(): void
    {
        $tools = MarketCategory::factory()->create(['name' => 'Tools']);
        $books = MarketCategory::factory()->create(['name' => 'Books']);

        $matching = MarketListing::factory()->create([
            'title' => 'Hand saw',
            'market_category_id' => $tools->id,
            'listing_type' => MarketListingType::GiveAway,
            'condition' => MarketListingCondition::Good,
        ]);
        MarketListing::factory()->create([
            'title' => 'Garden novel',
            'market_category_id' => $books->id,
            'listing_type' => MarketListingType::Sell,
            'condition' => MarketListingCondition::New,
        ]);

        $this->get(route('market.index', [
            'category' => $tools->id,
            'type' => MarketListingType::GiveAway->value,
            'condition' => MarketListingCondition::Good->value,
        ]))
            ->assertOk()
            ->assertSee('Hand saw')
            ->assertDontSee('Garden novel');

        $this->assertTrue($matching->fresh()->status === MarketListingStatus::Published);
    }

    public function test_catalog_filters_by_location_name(): void
    {
        MarketListing::factory()->create([
            'title' => 'Erbil bicycle',
            'location_name' => 'Erbil citadel garden',
        ]);
        MarketListing::factory()->create([
            'title' => 'Duhok lamp',
            'location_name' => 'Duhok riverside',
        ]);

        $this->get(route('market.index', ['location' => 'Erbil']))
            ->assertOk()
            ->assertSee('Erbil bicycle')
            ->assertDontSee('Duhok lamp');
    }

    public function test_nearby_filter_uses_coordinates_and_ignores_listings_without_them(): void
    {
        MarketListing::factory()->create([
            'title' => 'Nearby stool',
            'latitude' => 36.1911,
            'longitude' => 44.0092,
        ]);
        MarketListing::factory()->create([
            'title' => 'Far stool',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);
        MarketListing::factory()->create([
            'title' => 'Unmapped stool',
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->get(route('market.index', [
            'near_lat' => 36.1911,
            'near_lng' => 44.0092,
            'radius_km' => 25,
        ]))
            ->assertOk()
            ->assertSee('Nearby stool')
            ->assertDontSee('Far stool')
            ->assertDontSee('Unmapped stool');
    }

    public function test_catalog_cards_show_a_photo_or_placeholder_with_the_offer_and_place(): void
    {
        $category = MarketCategory::factory()->create(['name' => 'Planters']);

        MarketListing::factory()->create([
            'title' => 'Cedar window box',
            'market_category_id' => $category->id,
            'listing_type' => MarketListingType::Sell,
            'price' => '18.00',
            'location_name' => 'Erbil garden',
            'featured_image' => 'market/featured/window-box.jpg',
        ]);
        MarketListing::factory()->create([
            'title' => 'Unpictured stool',
            'featured_image' => '',
            'location_name' => 'Duhok park',
        ]);

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSee('storage/market/featured/window-box.jpg', false)
            ->assertSee('aspect-[4/3]', false)
            ->assertSee('xl:grid-cols-4', false)
            ->assertSee('app-shell', false)
            ->assertSee('Cedar window box')
            ->assertSee('Price 18.00')
            ->assertSee('Erbil garden')
            ->assertSee('Planters')
            ->assertSee('Unpictured stool')
            ->assertSee('Duhok park')
            ->assertSee('No photo', false);
    }

    public function test_listing_page_uses_a_wide_photo_hero(): void
    {
        $withPhoto = MarketListing::factory()->create([
            'title' => 'Hero planter',
            'featured_image' => 'market/featured/hero.jpg',
        ]);
        $withoutPhoto = MarketListing::factory()->create([
            'title' => 'Plain stool',
            'featured_image' => '',
        ]);

        $this->get(route('market.show', $withPhoto))
            ->assertOk()
            ->assertSee('lg:aspect-[2/1]', false)
            ->assertSee('storage/market/featured/hero.jpg', false)
            ->assertSee('Hero planter');

        $this->get(route('market.show', $withoutPhoto))
            ->assertOk()
            ->assertSee('Plain stool')
            ->assertSee('No photo', false);
    }

    public function test_catalog_paginates_twelve_listings_per_page(): void
    {
        MarketListing::factory()->count(13)->create();

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSee('?page=2', false);

        $this->assertSame(12, $this->get(route('market.index'))->viewData('listings')->count());
        $this->assertSame(1, $this->get(route('market.index', ['page' => 2]))->viewData('listings')->count());
    }
}
