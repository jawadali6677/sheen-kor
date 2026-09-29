<?php

namespace Tests\Feature\Jobs;

use App\Actions\ModerateContent;
use App\Jobs\ModeratePostContent;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostModerationResult;
use App\Notifications\PostNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ModeratePostContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_post_queues_moderation_and_returns_the_pending_message(): void
    {
        Queue::fake([ModeratePostContent::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'Hello friends from the green valley today and tomorrow too',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('message', 'Your post is being checked. It will appear shortly.')
            ->assertJsonPath('html', null);

        $post = Post::query()->firstOrFail();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame($post->id, session('checking_post_id'));

        Queue::assertPushed(ModeratePostContent::class, function (ModeratePostContent $job) use ($post): bool {
            return $job->postId === $post->id
                && $job->contentVersion === ModeratePostContent::contentVersion($post);
        });
    }

    public function test_updating_a_post_queues_moderation_for_the_edited_content(): void
    {
        Queue::fake([ModeratePostContent::class]);

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'category_id' => null,
            'title' => 'Original notes from the community garden',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured_image' => null,
        ]);
        $previousVersion = ModeratePostContent::contentVersion($post);

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'title' => 'Updated notes from the community garden',
                'content' => str_repeat('We planted trees and cleaned the trail together. ', 4),
            ])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Your post is being checked. It will appear shortly.')
            ->assertSessionHas('checking_post_id', $post->id);

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertSame('Updated notes from the community garden', $post->title);

        Queue::assertPushed(ModeratePostContent::class, function (ModeratePostContent $job) use ($post, $previousVersion): bool {
            return $job->postId === $post->id
                && $job->contentVersion === ModeratePostContent::contentVersion($post)
                && $job->contentVersion !== $previousVersion;
        });
    }

    public function test_allowed_post_is_published_and_the_author_is_notified_once(): void
    {
        $this->fakeSightengine();

        $post = $this->pendingPost();
        $job = new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post));

        $job->handle(app(ModerateContent::class));
        $job->handle(app(ModerateContent::class));

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame(1, $post->user->notifications()->count());
        $this->assertSame(PostModerationResult::class, $post->user->notifications()->first()?->type);
        $this->assertSame('post_published', $post->user->notifications()->first()?->data['kind']);
        $this->assertSame($post->id, $post->user->notifications()->first()?->data['post_id']);
        Http::assertSentCount(1);
    }

    public function test_rejected_post_stays_unpublished_and_the_author_is_notified(): void
    {
        $this->fakeSightengine(text: $this->textPayload(['sexual' => 0.96]));

        $post = $this->pendingPost();

        (new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post)))
            ->handle(app(ModerateContent::class));

        $post->refresh();
        $notification = $post->user->notifications()->first();

        $this->assertSame('rejected', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame(PostModerationResult::class, $notification?->type);
        $this->assertSame('post_rejected', $notification?->data['kind']);
        $this->assertSame(PostModerationResult::RejectedBody, $notification?->data['body']);
    }

    public function test_uncertain_content_stays_pending_and_notifies_reviewers_once(): void
    {
        $this->fakeSightengine(text: $this->textPayload(['toxic' => 0.55]));

        $moderator = User::factory()->moderator()->create();
        $post = $this->pendingPost();
        $job = new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post));

        $job->handle(app(ModerateContent::class));
        $job->handle(app(ModerateContent::class));

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame(1, $moderator->notifications()->count());
        $this->assertSame(PostNeedsReview::class, $moderator->notifications()->first()?->type);
        $this->assertSame($post->id, $moderator->notifications()->first()?->data['post_id']);
        $this->assertSame(0, $post->user->notifications()->count());
    }

    public function test_moderation_check_failure_stays_pending_and_notifies_reviewers(): void
    {
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['status' => 'failure'], 500),
        ]);

        $moderator = User::factory()->moderator()->create();
        $post = $this->pendingPost();

        (new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post)))
            ->handle(app(ModerateContent::class));

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertSame(1, $moderator->notifications()->count());
        $this->assertSame(PostNeedsReview::class, $moderator->notifications()->first()?->type);
        $this->assertSame(0, $post->user->notifications()->count());
    }

    public function test_final_job_failure_leaves_the_post_pending_and_notifies_reviewers(): void
    {
        $moderator = User::factory()->moderator()->create();
        $post = $this->pendingPost();
        $job = new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post));

        $job->failed(new RuntimeException('moderation worker failed'));
        $job->failed(new RuntimeException('moderation worker failed'));

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame(1, $moderator->unreadNotifications()->count());
        $this->assertSame(PostNeedsReview::class, $moderator->notifications()->first()?->type);
        $this->assertSame(0, $post->user->notifications()->count());
    }

    public function test_final_job_failure_does_not_replace_a_published_post(): void
    {
        $moderator = User::factory()->moderator()->create();
        $post = $this->pendingPost([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        (new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post)))
            ->failed(new RuntimeException('moderation worker failed'));

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame(0, $moderator->notifications()->count());
    }

    public function test_deleted_post_is_skipped(): void
    {
        Http::preventStrayRequests();

        $post = $this->pendingPost();
        $job = new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post));
        $post->delete();

        $job->handle(app(ModerateContent::class));
        $job->failed(new RuntimeException('moderation worker failed'));

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        Http::assertNothingSent();
    }

    public function test_job_for_an_older_edit_does_not_change_the_post(): void
    {
        $this->fakeSightengine();

        $moderator = User::factory()->moderator()->create();
        $post = $this->pendingPost([
            'title' => 'Original notes from the community garden',
        ]);
        $job = new ModeratePostContent($post->id, ModeratePostContent::contentVersion($post));

        $post->update([
            'title' => 'A newer edit of the garden notes',
            'status' => 'pending',
            'published_at' => null,
        ]);

        $job->handle(app(ModerateContent::class));
        $job->failed(new RuntimeException('moderation worker failed'));

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertSame('A newer edit of the garden notes', $post->title);
        $this->assertSame(0, $moderator->notifications()->count());
        Http::assertNothingSent();
    }

    public function test_author_sees_a_simple_message_when_moderation_rejects_the_post(): void
    {
        $post = $this->pendingPost([
            'status' => 'rejected',
        ]);

        $this->actingAs($post->user)
            ->getJson(route('posts.moderation-status', $post))
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('message', 'Your post was not published. It did not follow our community rules.')
            ->assertJsonPath('html', null);
    }

    public function test_feed_shows_a_checking_card_while_a_new_post_is_still_pending(): void
    {
        Queue::fake([ModeratePostContent::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'simple_post' => '1',
                'content' => 'Hello friends from the green valley today and tomorrow too',
            ])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('checking_post_id');

        $post = Post::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('data-feed-post-status', false)
            ->assertSee('data-live-feed="1"', false)
            ->assertSee('Checking your post...', false)
            ->assertSee('data-status-url="'.route('posts.moderation-status', $post).'"', false);

        $this->actingAs($user)
            ->get(route('posts.index', ['q' => 'trees']))
            ->assertOk()
            ->assertDontSee('data-live-feed="1"', false);
    }

    public function test_author_sees_pending_moderation_status(): void
    {
        $post = $this->pendingPost();

        $this->actingAs($post->user)
            ->getJson(route('posts.moderation-status', $post))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Your post is being checked. It will appear shortly.',
                'status' => 'pending',
                'html' => null,
            ]);
    }

    public function test_author_sees_the_post_card_once_moderation_publishes_it(): void
    {
        $post = $this->pendingPost([
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($post->user)
            ->getJson(route('posts.moderation-status', $post));

        $response->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('message', 'Your post is live!');

        $html = $response->json('html');

        $this->assertIsString($html);
        $this->assertStringContainsString('data-post-id="'.$post->id.'"', $html);
    }

    public function test_other_user_receives_403_for_moderation_status(): void
    {
        $post = $this->pendingPost();

        $this->actingAs(User::factory()->moderator()->create())
            ->getJson(route('posts.moderation-status', $post))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_moderation_status(): void
    {
        $post = $this->pendingPost();

        $this->get(route('posts.moderation-status', $post))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingPost(array $overrides = []): Post
    {
        $author = User::factory()->create();

        return Post::factory()->create([
            'user_id' => $author->id,
            'category_id' => null,
            'title' => 'Neighbors planted trees along the river',
            'excerpt' => 'Neighbors gathered to plant saplings and pick up litter.',
            'content' => str_repeat('We planted trees and cleaned the trail together. ', 4),
            'featured_image' => null,
            'status' => 'pending',
            'published_at' => null,
            ...$overrides,
        ]);
    }

    private function withSightengineCredentials(): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $text
     */
    private function fakeSightengine(?array $text = null): void
    {
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/1.0/text/check.json' => Http::response($text ?? $this->textPayload()),
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
}
