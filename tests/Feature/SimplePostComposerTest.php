<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimplePostComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_only_post_is_saved_with_a_generated_title_and_points(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'Hello friends from the green valley today and tomorrow too',
            ])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your post is being checked.');

        $post = Post::query()->firstOrFail();

        $this->assertSame('Hello friends from the green valley today and', $post->title);
        $this->assertSame('Hello friends from the green valley today and tomorrow too', $post->content);
        $this->assertTrue($post->title_is_generated);
        $this->assertNotSame('', $post->slug);
        $this->assertNull($post->category_id);
        $this->assertSame(10, $user->fresh()->score);
    }

    public function test_photo_only_post_uses_the_photo_title_and_stores_empty_text(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Amina Shah']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => "  \n  ",
                'images' => [UploadedFile::fake()->image('river.jpg')],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->firstOrFail();

        $this->assertSame('Photo by Amina Shah', $post->title);
        $this->assertSame('', $post->content);
        $this->assertTrue($post->title_is_generated);
        $this->assertNotNull($post->featured_image);
        $this->assertSame(0, $post->images()->count());
        Storage::disk('public')->assertExists($post->featured_image);
    }

    public function test_video_only_post_uses_the_video_title(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Bilal Khan']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'videos' => [UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4')],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->firstOrFail();

        $this->assertSame('Video by Bilal Khan', $post->title);
        $this->assertSame('', $post->content);
        $this->assertTrue($post->title_is_generated);
        $this->assertNull($post->featured_image);
        $this->assertSame(1, $post->images()->where('media_type', 'video')->count());
    }

    public function test_text_and_photo_post_keeps_the_words_as_the_title(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'Look at the river',
                'featured_image' => UploadedFile::fake()->image('river.jpg'),
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->firstOrFail();

        $this->assertSame('Look at the river', $post->title);
        $this->assertSame('Look at the river', $post->content);
        $this->assertNotNull($post->featured_image);
        $this->assertTrue($post->title_is_generated);
    }

    public function test_empty_post_is_rejected_with_a_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => "  \n\t  ",
            ])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHasErrors([
                'content' => 'Please write something or add a photo or video.',
            ]);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_emoji_and_urdu_only_text_gets_a_fallback_slug(): void
    {
        $user = User::factory()->create();
        $emoji = '😀🎉✨';
        $symbols = '؟؟؟';
        $urdu = 'یہ اردو متن ہے';

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => $emoji,
            ])
            ->assertRedirect(route('posts.index'));

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => $symbols,
            ])
            ->assertRedirect(route('posts.index'));

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => $urdu,
            ])
            ->assertRedirect(route('posts.index'));

        $posts = Post::query()->orderBy('id')->get();

        $this->assertCount(3, $posts);
        $this->assertSame($emoji, $posts[0]->title);
        $this->assertSame($symbols, $posts[1]->title);
        $this->assertSame($urdu, $posts[2]->title);
        $this->assertSame('post', $posts[0]->slug);
        $this->assertSame('post-1', $posts[1]->slug);
        $this->assertSame('post-2', $posts[2]->slug);
    }

    public function test_skipped_category_defaults_to_community_by_slug_or_name(): void
    {
        $user = User::factory()->create();
        $community = Category::query()->create([
            'name' => 'Community',
            'slug' => 'community',
            'description' => 'Local updates',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'The well is open again',
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertSame($community->id, Post::query()->firstOrFail()->category_id);

        $community->update(['slug' => 'village-news']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'The school yard is clean',
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertSame(
            $community->id,
            Post::query()->where('content', 'The school yard is clean')->firstOrFail()->category_id,
        );
    }

    public function test_feed_page_two_does_not_include_the_composer_bar(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 11) as $number) {
            Post::factory()->create([
                'status' => 'published',
                'published_at' => now()->subMinutes($number),
                'title' => 'Feed story '.$number,
            ]);
        }

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('What\'s on your mind?', false)
            ->assertSee('data-feed-composer-bar', false);

        $this->actingAs($user)
            ->get(route('posts.index', ['partial' => 1, 'page' => 2]))
            ->assertOk()
            ->assertDontSee('What\'s on your mind?', false)
            ->assertDontSee('data-feed-composer-bar', false);
    }

    public function test_text_only_post_can_be_edited_without_a_title(): void
    {
        $user = User::factory()->create(['name' => 'Amina Shah']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'Hello from the village',
            ]);

        $post = Post::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'simple_post' => '1',
                'content' => 'Updated hello from the village',
            ])
            ->assertRedirect(route('posts.index'));

        $post->refresh();

        $this->assertSame('Updated hello from the village', $post->content);
        $this->assertSame('Updated hello from the village', $post->title);
        $this->assertTrue($post->title_is_generated);
        $this->assertNotSame('', $post->slug);
    }

    public function test_photo_only_post_can_be_saved_again_and_removing_everything_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Amina Shah']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'images' => [UploadedFile::fake()->image('tree.jpg')],
            ]);

        $post = Post::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'simple_post' => '1',
                'content' => '',
            ])
            ->assertRedirect(route('posts.index'));

        $post->refresh();

        $this->assertSame('Photo by Amina Shah', $post->title);
        $this->assertNotNull($post->featured_image);

        $this->actingAs($user)
            ->from(route('posts.edit', $post))
            ->put(route('posts.update', $post), [
                'simple_post' => '1',
                'content' => '   ',
                'remove_featured' => '1',
            ])
            ->assertRedirect(route('posts.edit', $post))
            ->assertSessionHasErrors([
                'content' => 'Please write something or add a photo or video.',
            ]);

        $this->assertNotNull($post->fresh()->featured_image);
    }

    public function test_status_posts_show_the_body_instead_of_the_generated_title(): void
    {
        $user = User::factory()->create();

        $textPost = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now(),
            'title_is_generated' => true,
            'title' => 'Hello from the valley',
            'excerpt' => null,
            'content' => 'Hello from the valley',
            'featured_image' => null,
        ]);

        $photoPost = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'title_is_generated' => true,
            'title' => 'Photo by Sam',
            'excerpt' => null,
            'content' => 'River picnic',
            'featured_image' => 'posts/featured/picnic.jpg',
        ]);

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('data-post-layout="status"', false)
            ->assertSee('Hello from the valley')
            ->assertSee('River picnic')
            ->assertDontSee('>Photo by Sam<', false)
            ->assertDontSee('Read more');

        $this->get(route('posts.show', $textPost))
            ->assertOk()
            ->assertSee('data-post-layout="status"', false)
            ->assertSee('Hello from the valley')
            ->assertDontSee('text-3xl font-bold', false);

        $this->get(route('posts.show', $photoPost))
            ->assertOk()
            ->assertSee('River picnic')
            ->assertDontSee('>Photo by Sam<', false);
    }
}
