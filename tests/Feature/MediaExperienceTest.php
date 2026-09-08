<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertImage;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_only_posts_use_an_article_card_instead_of_an_empty_image(): void
    {
        $post = Post::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'featured_image' => null,
            'title' => 'Why Clean Communities Matter',
            'excerpt' => 'Clean communities are not only nicer to live in.',
        ]);

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Why Clean Communities Matter')
            ->assertSee('Read more')
            ->assertDontSee('bg-forest-800 px-6 text-center', false);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Why Clean Communities Matter')
            ->assertDontSee('js-lightbox', false);
    }

    public function test_posts_with_multiple_images_show_a_carousel_counter(): void
    {
        $post = Post::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'featured_image' => 'posts/featured/cover.jpg',
            'title' => 'River cleanup day',
        ]);

        PostImage::factory()->create([
            'post_id' => $post->id,
            'image' => 'posts/images/one.jpg',
            'sort_order' => 0,
            'media_type' => 'image',
        ]);
        PostImage::factory()->create([
            'post_id' => $post->id,
            'image' => 'posts/images/two.jpg',
            'sort_order' => 1,
            'media_type' => 'image',
        ]);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee(' / 3', false)
            ->assertSee('Previous media')
            ->assertSee('Next media');
    }

    public function test_alert_show_page_uses_a_vertical_layout_with_a_full_width_map(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create([
            'title' => 'Dumping beside the orchard',
            'featured_image' => 'alerts/featured/cover.jpg',
        ]);

        AlertImage::factory()->create([
            'alert_id' => $alert->id,
            'image' => 'alerts/images/extra.jpg',
            'kind' => 'report',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->assertSee('Dumping beside the orchard')
            ->assertSee('location-map-prominent', false)
            ->assertSee(' / 2', false)
            ->assertDontSee('lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]', false)
            ->assertSee('data-confirm="Take this alert?"', false);
    }

    public function test_post_and_alert_feeds_load_the_next_page_as_a_html_fragment(): void
    {
        foreach (range(1, 11) as $number) {
            Post::factory()->create([
                'status' => 'published',
                'published_at' => now(),
                'title' => 'Feed story '.$number,
            ]);
            Alert::factory()->create([
                'title' => 'Feed alert '.$number,
            ]);
        }

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('No more posts')
            ->assertDontSee('>Next</span>', false);

        $this->get(route('posts.index', ['partial' => 1, 'page' => 2]))
            ->assertOk()
            ->assertSee('Feed story 1')
            ->assertDontSee("What's happening around you?");

        $this->get(route('alerts.index', ['partial' => 1, 'page' => 2]))
            ->assertOk()
            ->assertSee('Feed alert 1')
            ->assertDontSee('Environmental Alerts');
    }

    public function test_create_forms_use_a_unified_media_uploader(): void
    {
        $user = User::factory()->create();
        Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-media',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->get(route('posts.create'))
            ->assertOk()
            ->assertSee('Add photos & videos')
            ->assertDontSee('Featured Image')
            ->assertDontSee('Additional Images');

        $this->actingAs($user)
            ->get(route('alerts.create'))
            ->assertOk()
            ->assertSee('Add photos & videos')
            ->assertDontSee('Photo (required)');
    }

    public function test_delete_actions_use_a_styled_confirmation_instead_of_browser_confirm(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('data-confirm="Delete this post?"', false)
            ->assertSee('id="sk-confirm-title"', false)
            ->assertDontSee('onsubmit="return confirm(', false);
    }
}
