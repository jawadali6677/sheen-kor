<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Category;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentSlugUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_show_urls_use_slugs_not_numeric_ids(): void
    {
        $post = Post::factory()->create([
            'category_id' => $this->category()->id,
            'status' => 'published',
            'published_at' => now(),
            'slug' => 'river-cleanup-story',
            'title' => 'River cleanup story',
        ]);
        $alert = Alert::factory()->create([
            'slug' => 'illegal-dumping-alert',
            'title' => 'Illegal dumping alert',
        ]);
        $listing = MarketListing::factory()->create([
            'slug' => 'cedar-planter-listing',
            'title' => 'Cedar planter listing',
        ]);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('River cleanup story');
        $this->get(route('alerts.show', $alert))
            ->assertOk()
            ->assertSee('Illegal dumping alert');
        $this->get(route('market.show', $listing))
            ->assertOk()
            ->assertSee('Cedar planter listing');

        $this->assertSame(url('/posts/river-cleanup-story'), route('posts.show', $post));
        $this->assertSame(url('/alerts/illegal-dumping-alert'), route('alerts.show', $alert));
        $this->assertSame(url('/market/cedar-planter-listing'), route('market.show', $listing));

        $this->get('/posts/'.$post->id)->assertNotFound();
        $this->get('/alerts/'.$alert->id)->assertNotFound();
        $this->get('/market/'.$listing->id)->assertNotFound();
    }

    public function test_unpublished_posts_cannot_be_opened_by_guests_or_other_users(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $this->category()->id,
            'status' => 'pending',
            'published_at' => null,
            'slug' => 'pending-forest-story',
        ]);

        $this->get(route('posts.show', $post))->assertNotFound();
        $this->get('/posts/'.$post->id)->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('posts.show', $post))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('posts.show', $post))
            ->assertOk();
    }

    public function test_pending_market_listings_cannot_be_opened_by_guests_or_other_users(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
            'slug' => 'pending-cedar-planter',
        ]);

        $this->get(route('market.show', $listing))->assertNotFound();
        $this->get('/market/'.$listing->id)->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('market.show', $listing))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('market.show', $listing))
            ->assertOk();
    }

    public function test_non_public_alerts_are_hidden_from_guests_and_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $alert = Alert::factory()->make([
            'user_id' => $owner->id,
            'status' => 'pending',
        ]);

        $this->assertFalse($alert->isPubliclyVisible());
        $this->assertFalse($other->can('view', $alert));
        $this->assertTrue($owner->can('view', $alert));
        $this->assertTrue($moderator->can('view', $alert));
    }

    public function test_admin_market_and_post_review_urls_stay_numeric(): void
    {
        $post = Post::factory()->create([
            'category_id' => $this->category()->id,
            'status' => 'pending',
            'slug' => 'needs-review-story',
        ]);
        $listing = MarketListing::factory()->pending()->create([
            'slug' => 'needs-review-listing',
        ]);

        $this->assertSame(url('/admin/posts/'.$post->id), route('admin.posts.show', $post));
        $this->assertSame(url('/admin/market/'.$listing->id), route('admin.market.show', $listing));
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-slug-urls',
            'status' => true,
        ]);
    }
}
