<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
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
    }

    public function test_a_member_cannot_follow_an_inactive_user(): void
    {
        $member = User::factory()->create();
        $inactive = User::factory()->disabled()->create();

        $this->postJson(route('api.v1.users.follow.store', $inactive), [], $this->bearer($member))
            ->assertForbidden();

        $this->assertFalse($member->fresh()->isFollowing($inactive));
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
