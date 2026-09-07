<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_landing_page_shows_sheen_kor_hero(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Together for a')
            ->assertSee('Greener Tomorrow')
            ->assertSee('Join Sheen Kor')
            ->assertSee('SHEEN KOR');
    }

    public function test_authenticated_users_are_sent_from_landing_to_the_feed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('posts.index'));
    }

    public function test_guests_can_read_posts_and_alerts(): void
    {
        $post = Post::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Public forest story',
        ]);
        $alert = Alert::factory()->create([
            'title' => 'Public river alert',
        ]);

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Public forest story');

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Public forest story');

        $this->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Public river alert');

        $this->get(route('alerts.show', $alert))
            ->assertOk()
            ->assertSee('Public river alert');
    }

    public function test_guests_cannot_create_posts_or_alerts(): void
    {
        $this->get(route('posts.create'))->assertRedirect(route('login'));
        $this->get(route('alerts.create'))->assertRedirect(route('login'));
    }

    public function test_about_and_tips_pages_are_public(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('About Sheen Kor', false);

        $this->get(route('tips.index'))
            ->assertOk();

        $this->assertDatabaseHas('categories', [
            'slug' => 'tips',
            'name' => 'Tips',
        ]);
    }

    public function test_explore_search_is_public(): void
    {
        Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature',
            'status' => true,
        ]);

        $this->get(route('explore.index', ['q' => 'Nature', 'tab' => 'categories']))
            ->assertOk()
            ->assertSee('Nature');
    }

    public function test_dashboard_redirects_authenticated_users_to_the_feed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('posts.index'));
    }
}
