<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_story_text_is_published_automatically(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'Neighbors planted trees along the river'))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your story has been published.');

        $post = Post::query()->firstOrFail();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/1.0/text/check.json')) {
                return false;
            }

            parse_str($request->body(), $body);

            return ($body['models'] ?? null) === 'general'
                && ($body['mode'] ?? null) === 'ml'
                && ! array_key_exists('categories', $body);
        });
    }

    public function test_safe_photos_of_people_at_a_cleanup_are_published(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine(image: $this->communityPhotoPayload());

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                ...$this->storyPayload($category, 'Families and workers cleaned the park together'),
                'featured_image' => UploadedFile::fake()->image('cleanup-crew.jpg'),
                'images' => [UploadedFile::fake()->image('planting-trees.jpg')],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->firstOrFail();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->featured_image);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1.0/check.json'));
    }

    public function test_safe_short_videos_are_published(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine(video: $this->safeVideoPayload());

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                ...$this->storyPayload($category, 'A short clip from the Saturday planting day'),
                'videos' => [UploadedFile::fake()->create('clip.mp4', 400, 'video/mp4')],
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertSame('published', Post::query()->firstOrFail()->status);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1.0/video/check-sync.json'));
    }

    public function test_sexual_story_text_is_rejected(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['sexual' => 0.96]));

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'This title is long enough to submit'))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your story was not published because it did not meet community guidelines.');

        $post = Post::query()->firstOrFail();

        $this->assertSame('rejected', $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_explicit_image_content_is_rejected(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine(image: $this->explicitImagePayload());

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                ...$this->storyPayload($category, 'A walk along the river bank today'),
                'featured_image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertSame('rejected', Post::query()->firstOrFail()->status);
    }

    public function test_uncertain_scores_keep_the_story_pending(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['toxic' => 0.55]));

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your story has been submitted successfully and is awaiting review.');

        $post = Post::query()->firstOrFail();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_moderation_api_failure_keeps_the_story_pending(): void
    {
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['status' => 'failure'], 500),
        ]);

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect(route('posts.index'));

        $this->assertSame('pending', Post::query()->firstOrFail()->status);
    }

    public function test_moderation_connection_failure_keeps_the_story_pending(): void
    {
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::failedConnection(),
        ]);

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect(route('posts.index'));

        $this->assertSame('pending', Post::query()->firstOrFail()->status);
    }

    public function test_missing_moderation_credentials_keep_the_story_pending(): void
    {
        config([
            'services.sightengine.user' => '',
            'services.sightengine.secret' => '',
        ]);
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $category = $this->natureCategory();

        $this->actingAs($user)
            ->post(route('posts.store'), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect(route('posts.index'));

        $this->assertSame('pending', Post::query()->firstOrFail()->status);
        Http::assertNothingSent();
    }

    public function test_editing_a_published_story_rechecks_content_before_it_stays_published(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $user = User::factory()->create();
        $category = $this->natureCategory();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured_image' => null,
        ]);

        $this->actingAs($user)
            ->put(route('posts.update', $post), $this->storyPayload($category, 'Updated notes from the community garden'))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your story has been updated and published.');

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_editing_a_published_story_rejects_unsafe_new_text(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['insulting' => 0.91]));

        $user = User::factory()->create();
        $category = $this->natureCategory();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured_image' => null,
        ]);

        $this->actingAs($user)
            ->put(route('posts.update', $post), $this->storyPayload($category, 'Updated notes from the community garden'))
            ->assertRedirect(route('posts.index'));

        $post->refresh();

        $this->assertSame('rejected', $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_editing_a_published_story_stays_pending_when_moderation_fails(): void
    {
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['status' => 'failure'], 503),
        ]);

        $user = User::factory()->create();
        $category = $this->natureCategory();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured_image' => null,
        ]);

        $this->actingAs($user)
            ->put(route('posts.update', $post), $this->storyPayload($category, 'Updated notes from the community garden'))
            ->assertRedirect(route('posts.index'));

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
    }

    private function withSightengineCredentials(): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);
    }

    /**
     * @param  array<string, float>  $classes
     * @param  array<string, mixed>  $image
     * @param  array<string, mixed>  $video
     */
    private function fakeSightengine(?array $text = null, ?array $image = null, ?array $video = null): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/1.0/text/check.json' => Http::response($text ?? $this->textPayload()),
            'api.sightengine.com/1.0/check.json' => Http::response($image ?? $this->communityPhotoPayload()),
            'api.sightengine.com/1.0/video/check-sync.json' => Http::response($video ?? $this->safeVideoPayload()),
        ]);
    }

    /**
     * @param  array<string, float>  $overrides
     * @return array<string, mixed>
     */
    private function textPayload(array $overrides = []): array
    {
        return [
            'status' => 'success',
            'moderation_classes' => array_merge([
                'sexual' => 0.01,
                'discriminatory' => 0.01,
                'insulting' => 0.01,
                'violent' => 0.01,
                'toxic' => 0.02,
                'spam' => 0.01,
            ], $overrides),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communityPhotoPayload(): array
    {
        return [
            'status' => 'success',
            'nudity' => [
                'sexual_activity' => 0.01,
                'sexual_display' => 0.01,
                'erotica' => 0.02,
                'very_suggestive' => 0.05,
                'suggestive' => 0.18,
                'mildly_suggestive' => 0.22,
                'none' => 0.81,
                'suggestive_classes' => [
                    'cleavage' => 0.12,
                    'male_chest' => 0.08,
                ],
            ],
            'faces' => [
                ['prob' => 0.99],
            ],
            'offensive' => ['prob' => 0.02],
            'gore' => ['prob' => 0.01],
            'violence' => ['prob' => 0.02],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function explicitImagePayload(): array
    {
        return [
            'status' => 'success',
            'nudity' => [
                'sexual_activity' => 0.94,
                'sexual_display' => 0.88,
                'erotica' => 0.91,
                'very_suggestive' => 0.95,
                'none' => 0.02,
            ],
            'offensive' => ['prob' => 0.11],
            'gore' => ['prob' => 0.01],
            'violence' => ['prob' => 0.01],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeVideoPayload(): array
    {
        return [
            'status' => 'success',
            'data' => [
                'frames' => [
                    $this->communityPhotoPayload(),
                ],
            ],
        ];
    }

    private function natureCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-moderation-'.fake()->unique()->numerify('####'),
            'description' => 'Nature stories',
            'status' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storyPayload(Category $category, string $title): array
    {
        return [
            'title' => $title,
            'category_id' => $category->id,
            'excerpt' => 'Neighbors gathered to plant saplings and pick up litter.',
            'content' => str_repeat('We planted trees and cleaned the trail together. ', 4),
        ];
    }
}
