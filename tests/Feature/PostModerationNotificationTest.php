<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostModerationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_review_notifies_users_with_moderate_posts_once(): void
    {
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['toxic' => 0.55]));

        $author = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $extra = User::factory()->create();
        $extra->syncExtraPermissions([Permission::ModeratePosts->value]);
        $member = User::factory()->create();
        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $category = $this->natureCategory();

        $this->actingAs($author)
            ->post(route('posts.store'), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->firstOrFail();

        $this->assertSame('pending', $post->status);
        $this->assertSame(1, $moderator->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $extra->notifications()->count());
        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(0, $disabledAdmin->notifications()->count());
        $this->assertSame(PostNeedsReview::class, $moderator->notifications()->first()?->type);
        $this->assertSame('post_needs_review', $moderator->notifications()->first()?->data['kind']);
        $this->assertSame(route('admin.posts.show', $post), $moderator->notifications()->first()?->data['url']);

        $this->actingAs($author)
            ->put(route('posts.update', $post), $this->storyPayload($category, 'A walk along the river bank today'))
            ->assertRedirect();

        $this->assertSame(1, $moderator->fresh()->unreadNotifications()->count());
    }

    public function test_automatic_publish_and_reject_do_not_notify_reviewers(): void
    {
        $this->withSightengineCredentials();
        $moderator = User::factory()->moderator()->create();
        $category = $this->natureCategory();

        $this->fakeSightengine();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->storyPayload($category, 'Neighbors planted trees along the river'))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(0, $moderator->notifications()->count());

        $this->fakeSightengine(text: $this->textPayload(['sexual' => 0.96]));

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->storyPayload($category, 'This title is long enough to submit'))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(0, $moderator->fresh()->notifications()->count());
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
            'api.sightengine.com/1.0/check.json' => Http::response($image ?? $this->safeImagePayload()),
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
    private function safeImagePayload(): array
    {
        return [
            'status' => 'success',
            'nudity' => [
                'sexual_activity' => 0.01,
                'sexual_display' => 0.01,
                'erotica' => 0.01,
                'very_suggestive' => 0.01,
                'none' => 0.99,
            ],
            'offensive' => ['prob' => 0.01],
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
                    $this->safeImagePayload(),
                ],
            ],
        ];
    }

    private function natureCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-notify-'.fake()->unique()->numerify('####'),
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
