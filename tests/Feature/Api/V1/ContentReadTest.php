<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ListingPromotionPlacement;
use App\Enums\MarketListingType;
use App\Models\Alert;
use App\Models\Category;
use App\Models\ListingPromotion;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentReadTest extends TestCase
{
    use RefreshDatabase;

    private const string BrowserUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_a_guest_can_read_the_feed_without_a_token(): void
    {
        $category = $this->storyCategory();
        Post::factory()->create([
            'title' => 'Published river story',
            'category_id' => $category->id,
            'status' => 'published',
            'created_at' => '2026-10-05 12:00:00',
        ]);
        Post::factory()->create([
            'title' => 'Pending river story',
            'category_id' => $category->id,
            'status' => 'pending',
            'published_at' => null,
            'created_at' => '2026-10-06 12:00:00',
        ]);

        $this->getJson(route('api.v1.posts.index'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Published river story')
            ->assertJsonPath('data.0.liked_by_user', false)
            ->assertJsonPath('data.0.is_boosted', false);

        $this->getJson(route('api.v1.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'stories');
    }

    public function test_the_feed_is_newest_first_and_a_token_marks_liked_posts(): void
    {
        $older = Post::factory()->create([
            'title' => 'Older story',
            'created_at' => '2026-10-01 00:00:00',
        ]);
        $newer = Post::factory()->create([
            'title' => 'Newer story',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $viewer = User::factory()->create();
        $newer->likes()->create(['user_id' => $viewer->id]);
        PostBoost::factory()->active()->create([
            'user_id' => $newer->user_id,
            'post_id' => $newer->id,
            'package_id' => null,
        ]);
        $token = $viewer->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.posts.index'), $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Newer story')
            ->assertJsonPath('data.0.liked_by_user', true)
            ->assertJsonPath('data.0.is_boosted', true)
            ->assertJsonPath('data.1.title', 'Older story')
            ->assertJsonPath('data.1.liked_by_user', false)
            ->assertJsonPath('data.1.is_boosted', false);

        $this->assertNotSame($older->id, $newer->id);
    }

    public function test_the_feed_filters_by_search_and_active_category(): void
    {
        $tips = Category::query()->create([
            'name' => 'Tips',
            'slug' => 'tips',
            'status' => true,
        ]);
        $stories = $this->storyCategory();
        Post::factory()->create([
            'title' => 'Compost tips',
            'category_id' => $tips->id,
            'content' => 'Turn the pile each week.',
        ]);
        Post::factory()->create([
            'title' => 'River walk',
            'category_id' => $stories->id,
            'content' => 'A quiet morning.',
        ]);

        $this->getJson(route('api.v1.posts.index', ['q' => 'compost']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Compost tips');

        $this->getJson(route('api.v1.posts.index', ['category' => 'tips']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Compost tips');
    }

    public function test_unpublished_posts_are_hidden_from_guests_and_other_members(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Draft story',
            'status' => 'pending',
            'published_at' => null,
            'views' => 3,
        ]);

        $this->getJson(route('api.v1.posts.show', $post))->assertNotFound();
        $this->getJson(route('api.v1.posts.show', $post), $this->bearer($other->createToken('Pixel 8', ['mobile'])->plainTextToken))
            ->assertNotFound();

        $this->assertSame(3, (int) $post->fresh()->views);
    }

    public function test_an_author_can_read_their_unpublished_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'pending',
            'published_at' => null,
            'views' => 3,
        ]);

        $this->getJson(route('api.v1.posts.show', $post), [
            'Authorization' => 'Bearer '.$author->createToken('Pixel 8', ['mobile'])->plainTextToken,
            'User-Agent' => self::BrowserUserAgent,
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.views', 4);

        $this->assertSame(0, PostView::query()->count());
    }

    public function test_opening_a_post_counts_a_view_and_a_qualified_view_for_a_logged_in_reader(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'views' => 4,
            'status' => 'published',
        ]);
        $token = $reader->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.posts.show', $post), [
            'Authorization' => 'Bearer '.$token,
            'User-Agent' => self::BrowserUserAgent,
        ])->assertOk()
            ->assertJsonPath('data.views', 5);

        $this->assertSame(5, (int) $post->fresh()->views);
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'user_id' => $author->id,
            'viewer_user_id' => $reader->id,
            'viewer_key' => 'user:'.$reader->id,
        ]);
    }

    public function test_a_guest_view_increments_the_counter_and_does_not_record_a_qualified_view(): void
    {
        $post = Post::factory()->create([
            'views' => 4,
            'status' => 'published',
        ]);

        $this->getJson(route('api.v1.posts.show', $post), [
            'User-Agent' => self::BrowserUserAgent,
        ])->assertOk()
            ->assertJsonPath('data.views', 5)
            ->assertJsonPath('data.liked_by_user', false);

        $this->assertSame(0, PostView::query()->count());
    }

    public function test_an_invalid_token_on_a_public_read_returns_401(): void
    {
        $this->getJson(route('api.v1.posts.index'), [
            'Authorization' => 'Bearer 1|not-a-real-token',
        ])->assertUnauthorized();
    }

    public function test_a_disabled_token_on_a_public_read_returns_403_and_deletes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $user->forceFill(['status' => false])->save();

        $this->getJson(route('api.v1.posts.index'), $this->bearer($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_guest_can_read_alerts_and_opening_one_counts_a_view(): void
    {
        $older = Alert::factory()->create([
            'title' => 'Older spill',
            'status' => 'open',
            'created_at' => '2026-10-01 00:00:00',
        ]);
        $newer = Alert::factory()->create([
            'title' => 'Newer spill',
            'status' => 'fixed',
            'views' => 2,
            'created_at' => '2026-10-06 00:00:00',
        ]);

        $this->getJson(route('api.v1.alerts.index', ['status' => 'fixed', 'q' => 'spill']))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Newer spill')
            ->assertJsonPath('data.0.liked_by_user', false);

        $this->getJson(route('api.v1.alerts.show', $newer))
            ->assertOk()
            ->assertJsonPath('data.views', 3);

        $this->assertSame(3, (int) $newer->fresh()->views);
        $this->assertSame(0, (int) $older->fresh()->views - (int) Alert::query()->find($older->id)->views);
    }

    public function test_market_catalog_matches_the_website_filters_and_puts_promoted_listings_first(): void
    {
        $tools = MarketCategory::factory()->create(['name' => 'Tools', 'is_active' => true]);
        MarketCategory::factory()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $promoted = MarketListing::factory()->create([
            'title' => 'Promoted saw',
            'market_category_id' => $tools->id,
            'listing_type' => MarketListingType::GiveAway,
            'published_at' => '2026-10-01 00:00:00',
            'latitude' => 36.1911,
            'longitude' => 44.0092,
            'location_name' => 'Erbil garden',
        ]);
        MarketListing::factory()->create([
            'title' => 'Plain hammer',
            'market_category_id' => $tools->id,
            'published_at' => '2026-10-06 00:00:00',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'location_name' => 'London',
        ]);
        MarketListing::factory()->pending()->create([
            'title' => 'Pending drill',
            'market_category_id' => $tools->id,
        ]);
        ListingPromotion::factory()->active()->create([
            'user_id' => $promoted->user_id,
            'market_listing_id' => $promoted->id,
            'package_id' => null,
            'placement' => ListingPromotionPlacement::FeaturedHome,
        ]);

        $this->getJson(route('api.v1.market.categories'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tools')
            ->assertJsonMissing(['slug' => 'hidden']);

        $this->getJson(route('api.v1.market.index'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.title', 'Promoted saw')
            ->assertJsonPath('data.0.is_promoted', true)
            ->assertJsonPath('data.1.title', 'Plain hammer')
            ->assertJsonPath('data.1.is_promoted', false);

        $this->getJson(route('api.v1.market.index', [
            'q' => 'saw',
            'category' => $tools->id,
            'type' => MarketListingType::GiveAway->value,
            'location' => 'Erbil',
            'near_lat' => 36.1911,
            'near_lng' => 44.0092,
            'radius_km' => 25,
        ]))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Promoted saw');
    }

    public function test_unpublished_listings_are_hidden_from_guests_and_visible_to_the_owner_and_an_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
            'title' => 'Pending stool',
        ]);

        $this->getJson(route('api.v1.market.show', $listing))->assertNotFound();

        $this->getJson(route('api.v1.market.show', $listing), $this->bearer($owner->createToken('Pixel 8', ['mobile'])->plainTextToken))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reported_by_user', false);

        $this->getJson(route('api.v1.market.show', $listing), $this->bearer($admin->createToken('Pixel 8', ['mobile'])->plainTextToken))
            ->assertOk()
            ->assertJsonPath('data.title', 'Pending stool');
    }

    private function storyCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Stories',
            'slug' => 'stories',
            'status' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
