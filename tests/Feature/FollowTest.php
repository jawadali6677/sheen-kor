<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_follow_users(): void
    {
        $user = User::factory()->create();

        $this->post(route('users.follow.store', $user))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_follow_and_unfollow_another_user(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($actor)
            ->from(route('users.show', $other))
            ->post(route('users.follow.store', $other))
            ->assertRedirect(route('users.show', $other));

        $this->assertTrue($actor->fresh()->isFollowing($other));

        $this->actingAs($actor)
            ->from(route('users.show', $other))
            ->post(route('users.follow.store', $other))
            ->assertRedirect(route('users.show', $other));

        $this->assertSame(1, $actor->followings()->count());

        $this->actingAs($actor)
            ->from(route('users.show', $other))
            ->delete(route('users.follow.destroy', $other))
            ->assertRedirect(route('users.show', $other));

        $this->assertFalse($actor->fresh()->isFollowing($other));
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('users.follow.store', $user))
            ->assertForbidden();
    }

    public function test_user_cannot_follow_an_inactive_user(): void
    {
        $actor = User::factory()->create();
        $inactive = User::factory()->disabled()->create();

        $this->actingAs($actor)
            ->post(route('users.follow.store', $inactive))
            ->assertForbidden();
    }

    public function test_profile_shows_follow_button_for_other_users(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('users.show', $other))
            ->assertOk()
            ->assertSee('Follow')
            ->assertSee('Message');
    }

    public function test_profile_shows_follow_back_when_the_other_user_already_follows_you(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        $other->followings()->attach($actor->id);

        $this->actingAs($actor)
            ->get(route('users.show', $other))
            ->assertOk()
            ->assertSee('Follow back');
    }
}
