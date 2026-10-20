<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_update_their_profile_and_an_email_change_clears_verification(): void
    {
        $user = User::factory()->create([
            'name' => 'Nali Ahmed',
            'username' => 'nali',
            'email' => 'nali@example.com',
        ]);
        $this->assertNotNull($user->email_verified_at);

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => 'Nali Ahmed',
            'username' => 'Nali.Ahmed',
            'email' => 'nali.new@example.com',
            'bio' => '  River keeper  ',
            'location' => ' Erbil ',
            'website' => ' https://example.com ',
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.name', 'Nali Ahmed')
            ->assertJsonPath('data.username', 'nali.ahmed')
            ->assertJsonPath('data.email', 'nali.new@example.com')
            ->assertJsonPath('data.email_verified_at', null)
            ->assertJsonPath('data.bio', 'River keeper')
            ->assertJsonPath('data.location', 'Erbil')
            ->assertJsonPath('data.website', 'https://example.com');

        $user->refresh();
        $this->assertSame('nali.new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('nali.ahmed', $user->username);
    }

    public function test_a_member_can_upload_an_avatar_with_method_spoofing(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'nali@example.com',
            'profile_image' => null,
        ]);

        $response = $this->post(route('api.v1.profile.update'), [
            '_method' => 'PATCH',
            'name' => 'Nali Ahmed',
            'email' => 'nali@example.com',
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ], $this->bearer($user));

        $response->assertOk()
            ->assertJsonPath('data.email_verified_at', $user->email_verified_at?->toIso8601String());

        $user->refresh();
        $this->assertNotNull($user->profile_image);
        $this->assertNotNull($user->email_verified_at);
        Storage::disk('public')->assertExists($user->profile_image);
        $this->assertStringContainsString($user->profile_image, (string) $response->json('data.avatar_url'));
    }

    public function test_profile_rules_reject_a_duplicate_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => $user->name,
            'email' => $other->email,
        ], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_image_rules_are_available_to_a_mobile_token_and_do_not_change_profile_payloads(): void
    {
        $this->getJson(route('api.v1.profile.image-rules'))
            ->assertUnauthorized();

        $user = User::factory()->create();
        $plain = $user->createToken('Pixel 8', ['other'])->plainTextToken;

        $this->getJson(route('api.v1.profile.image-rules'), $this->bearerToken($plain))
            ->assertForbidden();

        $this->getJson(route('api.v1.profile.image-rules'), $this->bearer($user))
            ->assertOk()
            ->assertExactJson([
                'profile_image' => [
                    'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                    'max_kilobytes' => 5120,
                ],
                'cover_image' => [
                    'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                    'max_kilobytes' => 5120,
                ],
            ]);

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonMissingPath('data.mimes')
            ->assertJsonMissingPath('data.max_kilobytes')
            ->assertJsonMissingPath('data.payout_eligibility');
    }

    public function test_an_invalid_profile_image_is_rejected_and_the_current_image_stays(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'profile_image' => 'profiles/avatars/kept.jpg',
        ]);
        Storage::disk('public')->put('profiles/avatars/kept.jpg', 'avatar');

        $this->post(route('api.v1.profile.update'), [
            '_method' => 'PATCH',
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_image');

        $this->assertSame('profiles/avatars/kept.jpg', $user->fresh()->profile_image);
        Storage::disk('public')->assertExists('profiles/avatars/kept.jpg');
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
