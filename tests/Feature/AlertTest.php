<?php

namespace Tests\Feature;

use App\Events\AlertEngagementUpdated;
use App\Models\Alert;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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

    public function test_liking_and_commenting_on_an_alert_broadcasts_engagement_counts(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create();

        Event::fake([AlertEngagementUpdated::class]);

        $this->actingAs($user)
            ->postJson(route('alerts.likes.store', $alert))
            ->assertOk();

        Event::assertDispatched(AlertEngagementUpdated::class, function (AlertEngagementUpdated $event) use ($alert): bool {
            return $event->alertId === $alert->id
                && $event->likesCount === 1;
        });

        $this->actingAs($user)
            ->postJson(route('alerts.comments.store', $alert), [
                'content' => 'I saw this too yesterday.',
            ])
            ->assertCreated();

        Event::assertDispatched(AlertEngagementUpdated::class, function (AlertEngagementUpdated $event) use ($alert): bool {
            return $event->alertId === $alert->id
                && $event->commentsCount === 1;
        });

        $this->actingAs($user)
            ->deleteJson(route('alerts.likes.destroy', $alert))
            ->assertOk();

        Event::assertDispatched(AlertEngagementUpdated::class, function (AlertEngagementUpdated $event) use ($alert): bool {
            return $event->alertId === $alert->id
                && $event->likesCount === 0;
        });
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
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('alerts.destroy', $alert))
            ->assertForbidden();

        $response = $this->actingAs($owner)
            ->put(route('alerts.update', $alert), [
                'title' => 'Updated alert title here',
                'description' => $alert->description,
                'location_name' => $alert->location_name,
                'severity' => 'low',
            ]);

        $alert->refresh();

        $response->assertRedirect(route('alerts.show', $alert));

        $this->assertSame('Updated alert title here', $alert->fresh()->title);
        $this->assertSame('open', $alert->fresh()->status);
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
            'status' => 'fixed',
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index', ['q' => 'harbor']))
            ->assertOk()
            ->assertSee('Oil spill in the harbor')
            ->assertDontSee('Cleared dumping site');

        $this->actingAs($user)
            ->get(route('alerts.index', ['status' => 'fixed']))
            ->assertOk()
            ->assertSee('Cleared dumping site')
            ->assertDontSee('Oil spill in the harbor');
    }

    public function test_user_can_take_action_and_mark_an_alert_fixed(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'open',
        ]);

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.take-action', $alert))
            ->assertRedirect(route('alerts.show', $alert));

        $alert->refresh();

        $this->assertSame('in_progress', $alert->status);
        $this->assertSame($helper->id, $alert->action_user_id);
        $this->assertNotNull($alert->action_taken_at);

        $this->actingAs($other)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.take-action', $alert))
            ->assertRedirect(route('alerts.show', $alert))
            ->assertSessionHas('error');

        $this->assertSame($helper->id, $alert->fresh()->action_user_id);

        $this->actingAs($other)
            ->post(route('alerts.mark-fixed', $alert))
            ->assertForbidden();

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert), [
                'fixed_location_name' => 'River bank, cleaned stretch',
                'fixed_latitude' => 35.1234,
                'fixed_longitude' => 44.5678,
                'fix_images' => [UploadedFile::fake()->image('after.jpg')],
            ])
            ->assertRedirect(route('alerts.show', $alert));

        $alert->refresh();

        $this->assertSame('fixed', $alert->status);
        $this->assertNotNull($alert->fixed_at);
        $this->assertSame('River bank, cleaned stretch', $alert->fixed_location_name);
        $this->assertSame(1, $alert->fixImages()->count());

        $this->actingAs($other)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.take-action', $alert))
            ->assertRedirect(route('alerts.show', $alert))
            ->assertSessionHas('error');
    }

    public function test_a_guest_and_the_reporter_cannot_mark_an_alert_fixed(): void
    {
        $reporter = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $reporter->id,
            'status' => 'open',
        ]);

        $this->post(route('alerts.mark-fixed', $alert))
            ->assertRedirect(route('login'));

        $this->actingAs($reporter)
            ->post(route('alerts.mark-fixed', $alert), [
                'fixed_latitude' => 35.1,
                'fixed_longitude' => 44.5,
                'fix_images' => [UploadedFile::fake()->image('after.jpg')],
            ])
            ->assertForbidden();

        $this->assertSame('open', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->fixed_at);
    }

    public function test_marking_an_alert_fixed_requires_a_proof_photo_and_coordinates(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert), [])
            ->assertRedirect(route('alerts.show', $alert))
            ->assertSessionHasErrors([
                'fix_images' => 'Add a proof photo to mark this alert as fixed.',
                'fixed_latitude' => 'Add the latitude and longitude of the place you fixed.',
                'fixed_longitude' => 'Add the latitude and longitude of the place you fixed.',
            ]);

        $this->assertSame('in_progress', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->fixed_at);
        $this->assertSame(0, $helper->fresh()->score);
        $this->assertSame(0, $alert->fixImages()->count());
    }

    public function test_marking_an_alert_fixed_rejects_an_invalid_photo_or_coordinates(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert), [
                'fixed_latitude' => 91,
                'fixed_longitude' => -181,
                'fix_images' => [UploadedFile::fake()->image('after.gif')],
            ])
            ->assertRedirect(route('alerts.show', $alert))
            ->assertSessionHasErrors([
                'fix_images.0' => 'Proof photos must be JPEG, PNG, or WebP.',
                'fixed_latitude' => 'Latitude must be between -90 and 90.',
                'fixed_longitude' => 'Longitude must be between -180 and 180.',
            ]);

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert), [
                'fixed_latitude' => 35.1,
                'fixed_longitude' => 44.5,
                'fix_images' => [UploadedFile::fake()->image('huge.jpg')->size(5121)],
            ])
            ->assertRedirect(route('alerts.show', $alert))
            ->assertSessionHasErrors([
                'fix_images.0' => 'Each proof photo must be 5 MB or smaller.',
            ]);

        $this->assertSame('in_progress', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->fixed_at);
        $this->assertSame(0, $alert->fixImages()->count());
        Storage::disk('public')->assertDirectoryEmpty('alerts/fixes');
    }
}
