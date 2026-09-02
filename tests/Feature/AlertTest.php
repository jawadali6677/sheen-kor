<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_alerts_index(): void
    {
        $user = User::factory()->create();
        Alert::factory()->create([
            'title' => 'Illegal dumping near the river',
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Illegal dumping near the river')
            ->assertSee('js-like-button', false)
            ->assertSee('id="commentModal"', false);
    }

    public function test_user_can_create_an_alert(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $photo = UploadedFile::fake()->image('dump.jpg');

        $this->actingAs($user)
            ->post(route('alerts.store'), [
                'title' => 'Smoke from burning waste',
                'description' => 'Thick smoke is coming from a pile of burning plastic near the forest edge.',
                'location_name' => 'North forest road',
                'severity' => 'high',
                'featured_image' => $photo,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('alerts', [
            'user_id' => $user->id,
            'title' => 'Smoke from burning waste',
            'status' => 'open',
            'severity' => 'high',
        ]);
    }

    public function test_alert_requires_photo_and_location(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('alerts.store'), [
                'title' => 'Bad',
                'description' => 'Too short',
            ])
            ->assertSessionHasErrors(['title', 'description', 'location_name', 'severity', 'featured_image']);
    }

    public function test_user_can_like_and_comment_on_an_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create();

        $this->actingAs($user)
            ->postJson(route('alerts.likes.store', $alert))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'liked' => true,
                'likes_count' => 1,
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_id' => $alert->id,
            'likeable_type' => 'alert',
        ]);

        $this->actingAs($user)
            ->postJson(route('alerts.comments.store', $alert), [
                'content' => 'I saw this too yesterday.',
            ])
            ->assertCreated()
            ->assertJsonPath('comment.content', 'I saw this too yesterday.');

        $this->assertSame(1, Comment::query()->where('commentable_id', $alert->id)->where('commentable_type', 'alert')->count());

        $this->actingAs($user)
            ->deleteJson(route('alerts.likes.destroy', $alert))
            ->assertOk()
            ->assertJsonPath('liked', false);

        $this->assertSame(0, Like::query()->where('likeable_id', $alert->id)->where('likeable_type', 'alert')->count());
    }

    public function test_only_owner_can_update_or_delete_an_alert(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Original alert title here',
        ]);

        $this->actingAs($other)
            ->put(route('alerts.update', $alert), [
                'title' => 'Hijacked alert title here',
                'description' => $alert->description,
                'location_name' => $alert->location_name,
                'severity' => 'low',
                'status' => 'resolved',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('alerts.destroy', $alert))
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('alerts.update', $alert), [
                'title' => 'Updated alert title here',
                'description' => $alert->description,
                'location_name' => $alert->location_name,
                'severity' => 'low',
                'status' => 'resolved',
            ])
            ->assertRedirect(route('alerts.show', $alert));

        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_alerts_can_be_filtered_by_search_and_status(): void
    {
        $user = User::factory()->create();

        Alert::factory()->create([
            'title' => 'Oil spill in the harbor',
            'status' => 'open',
        ]);

        Alert::factory()->create([
            'title' => 'Cleared dumping site',
            'status' => 'resolved',
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index', ['q' => 'harbor']))
            ->assertOk()
            ->assertSee('Oil spill in the harbor')
            ->assertDontSee('Cleared dumping site');

        $this->actingAs($user)
            ->get(route('alerts.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('Cleared dumping site')
            ->assertDontSee('Oil spill in the harbor');
    }
}
