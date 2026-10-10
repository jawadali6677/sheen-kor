<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ModeratePostContent;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_text_update_and_delete_a_post(): void
    {
        $this->fakeSightengine();
        $user = User::factory()->create();
        $category = $this->storyCategory();

        $created = $this->postJson(route('api.v1.posts.store'), [
            'title' => 'River trees',
            'content' => 'Neighbors planted trees along the river bank today.',
            'category_id' => $category->id,
        ], $this->bearer($user));

        $created->assertCreated()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('message', 'Your post is live!')
            ->assertJsonPath('data.title', 'River trees')
            ->assertJsonMissingPath('html');

        $post = Post::query()->firstOrFail();
        $this->assertSame('published', $post->status);
        $this->assertSame(10, $user->refresh()->score);

        $this->patchJson(route('api.v1.posts.update', $post), [
            'title' => 'More river trees',
            'content' => 'Neighbors planted more trees along the river bank today.',
            'category_id' => $category->id,
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.title', 'More river trees')
            ->assertJsonPath('status', 'published');

        $this->deleteJson(route('api.v1.posts.destroy', $post), [], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Your post has been deleted.');

        $this->assertModelMissing($post);
        $this->assertSame(0, $user->refresh()->score);
    }

    public function test_a_photo_only_post_can_be_updated_with_method_spoofing(): void
    {
        Storage::fake('public');
        $this->fakeSightengine();
        $user = User::factory()->create(['name' => 'Nali Ahmed']);

        $created = $this->post(route('api.v1.posts.store'), [
            'featured_image' => UploadedFile::fake()->image('creek.jpg'),
        ], $this->bearer($user));

        $created->assertCreated()
            ->assertJsonPath('data.title', 'Photo by Nali Ahmed');

        $post = Post::query()->firstOrFail();
        $this->assertNotNull($post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);

        $this->post(route('api.v1.posts.update', $post), [
            '_method' => 'PATCH',
            'content' => 'The creek after the cleanup.',
            'featured_image' => UploadedFile::fake()->image('creek-after.jpg'),
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.content', 'The creek after the cleanup.')
            ->assertJsonMissingPath('html');
    }

    public function test_an_empty_post_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.posts.store'), [], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'Please write something or add a photo or video.');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_create_dispatches_moderation_and_the_author_can_poll_status(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.posts.store'), [
            'content' => 'Neighbors planted trees along the river bank today.',
        ], $this->bearer($user))->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('message', 'Your post is being checked. It will appear shortly.');

        $post = Post::query()->firstOrFail();
        Queue::assertPushed(ModeratePostContent::class, function (ModeratePostContent $job) use ($post): bool {
            return $job->postId === $post->id;
        });

        $this->getJson(route('api.v1.posts.moderation-status', $post), $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('message', 'Your post is being checked. It will appear shortly.')
            ->assertJsonMissingPath('html');
    }

    public function test_status_polling_returns_the_published_message_without_html(): void
    {
        $this->fakeSightengine();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.posts.store'), [
            'content' => 'Neighbors planted trees along the river bank today.',
        ], $this->bearer($user))->assertCreated();

        $post = Post::query()->firstOrFail();

        $this->getJson(route('api.v1.posts.moderation-status', $post), $this->bearer($user))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Your post is live!',
                'status' => 'published',
            ]);
    }

    public function test_another_member_cannot_update_delete_or_poll_a_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->patchJson(route('api.v1.posts.update', $post), [
            'content' => 'This is not my story to edit at all.',
        ], $this->bearer($other))->assertForbidden();

        $this->deleteJson(route('api.v1.posts.destroy', $post), [], $this->bearer($other))
            ->assertForbidden();

        $this->getJson(route('api.v1.posts.moderation-status', $post), $this->bearer($other))
            ->assertForbidden();

        $this->assertModelExists($post);
    }

    public function test_a_disabled_account_cannot_create_a_post(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $user->forceFill(['status' => false])->save();

        $this->postJson(route('api.v1.posts.store'), [
            'content' => 'Neighbors planted trees along the river bank today.',
        ], $this->bearerToken($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, Post::query()->count());
    }

    private function storyCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Stories',
            'slug' => 'stories-'.fake()->unique()->numerify('###'),
            'status' => true,
        ]);
    }

    private function fakeSightengine(): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response([
                'status' => 'success',
                'moderation_classes' => [
                    'sexual' => 0.01,
                    'discriminatory' => 0.01,
                    'insulting' => 0.01,
                    'violent' => 0.01,
                    'toxic' => 0.02,
                    'spam' => 0.01,
                ],
                'nudity' => ['sexual_activity' => 0.01, 'sexual_display' => 0.01, 'erotica' => 0.01, 'very_suggestive' => 0.05, 'none' => 0.9],
                'offensive' => ['prob' => 0.01],
                'gore' => ['prob' => 0.01],
                'violence' => ['prob' => 0.01],
                'faces' => [],
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return $this->bearerToken($user->createToken('Pixel 8', ['mobile'])->plainTextToken);
    }

    /**
     * @return array<string, string>
     */
    private function bearerToken(string $token): array
    {
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
