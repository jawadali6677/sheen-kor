<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\UserFollowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_follow_and_unfollow_and_the_profile_reports_it(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create(['username' => 'nali']);

        $this->getJson(route('api.v1.users.show', $other))
            ->assertOk()
            ->assertJsonPath('data.is_following', false);

        $this->postJson(route('api.v1.users.follow.store', $other), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('following', true);

        $this->assertTrue($member->fresh()->isFollowing($other));

        $this->getJson(route('api.v1.users.show', $other), $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('data.username', 'nali')
            ->assertJsonPath('data.is_following', true)
            ->assertJsonMissingPath('data.email');

        $this->deleteJson(route('api.v1.users.follow.destroy', $other), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('following', false);

        $this->assertFalse($member->fresh()->isFollowing($other));

        $this->getJson(route('api.v1.users.show', $other), $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('data.is_following', false);
    }

    public function test_a_member_cannot_follow_themselves(): void
    {
        $member = User::factory()->create();

        $this->postJson(route('api.v1.users.follow.store', $member), [], $this->bearer($member))
            ->assertForbidden();

        $this->assertSame(0, $member->followings()->count());
        $this->assertSame(0, $member->notifications()->count());
    }

    public function test_following_returns_a_new_follower_notification_and_unfollowing_does_not(): void
    {
        $ali = User::factory()->create([
            'name' => 'Ali',
            'username' => 'ali',
            'profile_image' => 'profiles/avatars/ali.jpg',
        ]);
        $followed = User::factory()->create(['username' => 'nali']);

        $this->postJson(route('api.v1.users.follow.store', $followed), [], $this->bearer($ali))
            ->assertOk()
            ->assertJsonPath('following', true);

        $this->assertSame(1, $followed->notifications()->count());
        $this->assertSame(0, $ali->notifications()->count());
        $this->assertSame(UserFollowed::class, $followed->notifications()->first()?->type);

        $this->getJson(route('api.v1.notifications.index'), $this->bearer($followed))
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'user_followed')
            ->assertJsonPath('data.0.title', 'Ali followed you')
            ->assertJsonPath('data.0.body', '@ali')
            ->assertJsonPath('data.0.actor_id', $ali->id)
            ->assertJsonPath('data.0.actor_name', 'Ali')
            ->assertJsonPath('data.0.actor_username', 'ali')
            ->assertJsonPath('data.0.actor_avatar_url', asset('storage/profiles/avatars/ali.jpg'));

        $this->deleteJson(route('api.v1.users.follow.destroy', $followed), [], $this->bearer($ali))
            ->assertOk()
            ->assertJsonPath('following', false);

        $this->assertSame(1, $followed->fresh()->notifications()->count());
    }

    public function test_a_member_cannot_follow_an_inactive_user(): void
    {
        $member = User::factory()->create();
        $inactive = User::factory()->disabled()->create();

        $this->postJson(route('api.v1.users.follow.store', $inactive), [], $this->bearer($member))
            ->assertForbidden();

        $this->assertFalse($member->fresh()->isFollowing($inactive));
    }

    public function test_profile_and_current_user_payloads_return_follower_and_following_counts(): void
    {
        $profile = User::factory()->create([
            'username' => 'nali',
            'email' => 'nali@example.com',
        ]);
        $firstFollower = User::factory()->create();
        $secondFollower = User::factory()->create();
        $followed = User::factory()->create();
        $unrelated = User::factory()->create();

        $firstFollower->followings()->attach($profile->id);
        $secondFollower->followings()->attach($profile->id);
        $profile->followings()->attach($followed->id);
        $unrelated->followings()->attach($followed->id);

        $this->getJson(route('api.v1.users.show', $profile))
            ->assertOk()
            ->assertJsonPath('data.followers_count', 2)
            ->assertJsonPath('data.following_count', 1)
            ->assertJsonPath('data.is_following', false)
            ->assertJsonMissingPath('data.email');

        $this->getJson(route('api.v1.users.show', $profile), $this->bearer($firstFollower))
            ->assertOk()
            ->assertJsonPath('data.followers_count', 2)
            ->assertJsonPath('data.following_count', 1)
            ->assertJsonPath('data.is_following', true);

        $this->getJson(route('api.v1.users.show', $followed))
            ->assertOk()
            ->assertJsonPath('data.followers_count', 2)
            ->assertJsonPath('data.following_count', 0);

        $this->getJson(route('api.v1.auth.me'), $this->bearer($profile))
            ->assertOk()
            ->assertJsonPath('user.followers_count', 2)
            ->assertJsonPath('user.following_count', 1)
            ->assertJsonPath('user.email', 'nali@example.com');

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => $profile->name,
            'email' => 'nali@example.com',
        ], $this->bearer($profile))
            ->assertOk()
            ->assertJsonPath('data.followers_count', 2)
            ->assertJsonPath('data.following_count', 1)
            ->assertJsonPath('data.email', 'nali@example.com');

        $firstFollower->followings()->detach($profile->id);

        $this->getJson(route('api.v1.users.show', $profile))
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.following_count', 1);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$user->createToken('Pixel 8', ['mobile'])->plainTextToken,
            'Accept' => 'application/json',
        ];
    }
}
