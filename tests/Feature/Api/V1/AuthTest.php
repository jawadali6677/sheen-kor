<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_a_mobile_token_without_a_web_session(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.unread_notifications_count', 0);

        $user = User::query()->where('email', 'ada@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEmpty($user->username);
        $this->assertTrue($user->status);
        $this->assertGuest();
        $response->assertCookieMissing(config('session.cookie'));

        $token = $user->tokens()->first();
        $this->assertNotNull($token);
        $this->assertSame('Pixel 8', $token->name);
        $this->assertSame(['mobile'], $token->abilities);
        $this->assertIsString($response->json('token'));
    }

    public function test_register_rejects_a_duplicate_email_and_creates_no_user(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        $this->assertGuest();
    }

    public function test_login_returns_a_token_and_does_not_start_a_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'score' => 12,
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'ada@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.score', 12)
            ->assertJsonPath('user.has_green_tick', false);

        $this->assertGuest();
        $response->assertCookieMissing(config('session.cookie'));
        $this->assertSame('Pixel 8', $user->tokens()->first()?->name);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_422_for_a_wrong_password_and_issues_no_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertGuest();
    }

    public function test_login_returns_422_for_a_disabled_account_and_issues_no_token(): void
    {
        $user = User::factory()->disabled()->create(['email' => 'ada@example.com']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'ada@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This account has been disabled.');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertGuest();
    }

    public function test_login_returns_429_after_five_attempts_for_the_same_email_and_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.auth.login'), [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
                'device_name' => 'Pixel 8',
            ])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Pixel 8',
        ])->assertTooManyRequests();
    }

    public function test_register_returns_429_after_five_attempts_from_the_same_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.auth.register'), [])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.register'), [])->assertTooManyRequests();
    }

    public function test_forgot_password_returns_429_after_five_attempts_from_the_same_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.auth.forgot-password'), [
                'email' => 'missing@example.com',
            ])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => 'missing@example.com',
        ])->assertTooManyRequests();
    }

    public function test_me_requires_a_mobile_token_and_includes_the_unread_count(): void
    {
        $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();

        $user = User::factory()->create(['score' => 4]);
        $user->notifications()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'type' => 'App\\Notifications\\ContentLiked',
            'data' => ['kind' => 'post_liked', 'title' => 'Liked', 'body' => 'Hello'],
        ]);
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.score', 4)
            ->assertJsonPath('user.unread_notifications_count', 1);
    }

    public function test_a_token_without_the_mobile_ability_returns_403(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['other'])->plainTextToken;

        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))
            ->assertForbidden();
    }

    public function test_a_token_stays_valid_after_a_year(): void
    {
        $this->travelTo('2026-01-01 00:00:00');

        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->travelTo('2027-01-01 00:00:00');

        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $other = $user->createToken('iPhone', ['mobile'])->plainTextToken;

        $this->postJson(route('api.v1.auth.logout'), [], $this->bearer($current))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->getJson(route('api.v1.auth.me'), $this->bearer($current))->assertUnauthorized();
        $this->getJson(route('api.v1.auth.me'), $this->bearer($other))->assertOk();
    }

    public function test_logout_all_deletes_every_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $other = $user->createToken('iPhone', ['mobile'])->plainTextToken;

        $this->postJson(route('api.v1.auth.logout-all'), [], $this->bearer($current))
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->getJson(route('api.v1.auth.me'), $this->bearer($current))->assertUnauthorized();
        $this->getJson(route('api.v1.auth.me'), $this->bearer($other))->assertUnauthorized();
    }

    public function test_api_password_change_signs_out_other_devices_and_keeps_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $other = $user->createToken('iPhone', ['mobile'])->plainTextToken;

        $this->putJson(route('api.v1.auth.password'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], $this->bearer($current))->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
        $this->getJson(route('api.v1.auth.me'), $this->bearer($current))->assertOk();
        $this->getJson(route('api.v1.auth.me'), $this->bearer($other))->assertUnauthorized();
    }

    public function test_api_password_change_returns_422_for_the_wrong_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->putJson(route('api.v1.auth.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], $this->bearer($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_website_password_change_deletes_app_tokens_and_keeps_the_website_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/profile');

        $this->assertAuthenticated();
        $this->assertSame(0, $user->tokens()->count());
        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))->assertUnauthorized();
    }

    public function test_website_password_reset_deletes_app_tokens_and_keeps_the_website_session(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $websiteSession = session()->all();

        // The reset form is guest-only, so finish it the way a second browser would.
        session()->flush();
        Auth::forgetGuards();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        session()->flush();
        session()->put($websiteSession);
        Auth::forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('posts.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))->assertUnauthorized();
    }

    public function test_forgot_password_sends_the_website_reset_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => 'ada@example.com',
        ])->assertOk()
            ->assertJsonPath('message', __('passwords.sent'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_delete_account_requires_the_password_and_removes_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $user->createToken('iPhone', ['mobile']);

        $this->deleteJson(route('api.v1.auth.account.destroy'), [
            'password' => 'wrong-password',
        ], $this->bearer($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertModelExists($user);

        $this->deleteJson(route('api.v1.auth.account.destroy'), [
            'password' => 'password',
        ], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted.');

        $this->assertModelMissing($user);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_website_account_deletion_removes_app_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ])
            ->assertRedirect('/');

        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))->assertUnauthorized();
    }

    public function test_a_disabled_account_deletes_the_token_and_returns_403(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $user->forceFill(['status' => false])->save();

        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');

        $this->assertSame(0, $user->tokens()->count());
        $this->getJson(route('api.v1.auth.me'), $this->bearer($token))->assertUnauthorized();
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        // Sanctum's request guard keeps the user for the whole test process.
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }
}
