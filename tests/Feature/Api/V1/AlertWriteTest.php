<?php

namespace Tests\Feature\Api\V1;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlertWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_update_and_delete_an_alert(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $created = $this->post(route('api.v1.alerts.store'), $this->alertFields(), $this->bearer($user));

        $created->assertCreated()
            ->assertJsonPath('message', 'Your environmental alert has been posted. Others can now see it.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.title', 'Oil spill beside the footbridge')
            ->assertJsonPath('data.fixed_location_name', null)
            ->assertJsonPath('data.fixed_latitude', null)
            ->assertJsonPath('data.fixed_longitude', null);

        $alert = Alert::query()->firstOrFail();
        $this->assertSame(15, $user->refresh()->score);
        Storage::disk('public')->assertExists($alert->featured_image);

        $this->patch(route('api.v1.alerts.update', $alert), [
            ...$this->alertFields('Cleared spill beside the footbridge'),
            'featured_image' => UploadedFile::fake()->image('spill-update.jpg'),
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.title', 'Cleared spill beside the footbridge')
            ->assertJsonPath('message', 'The alert has been updated.');

        $this->deleteJson(route('api.v1.alerts.destroy', $alert), [], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'The alert has been deleted.');

        $this->assertModelMissing($alert);
        $this->assertSame(0, $user->refresh()->score);
    }

    public function test_an_alert_requires_a_featured_photo(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.alerts.store'), [
            'title' => 'Oil spill beside the footbridge',
            'description' => 'Dark oil is spreading from the drain into the stream.',
            'location_name' => 'South footbridge',
            'severity' => 'high',
        ], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('featured_image');

        $this->assertSame(0, Alert::query()->count());
    }

    public function test_claim_and_fix_follow_the_website_rules_and_scoring(): void
    {
        Storage::fake('public');
        $helper = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::factory()->create(['status' => 'open']);

        $this->postJson(route('api.v1.alerts.take-action', $alert), [], $this->bearer($helper))
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $alert->refresh();
        $this->assertSame($helper->id, $alert->action_user_id);

        $this->postJson(route('api.v1.alerts.take-action', $alert), [], $this->bearer($other))
            ->assertUnprocessable()
            ->assertJsonPath('errors.alert.0', 'This alert is already being handled by someone else.');

        $this->postJson(route('api.v1.alerts.mark-fixed', $alert), [], $this->bearer($other))
            ->assertForbidden();

        $this->post(route('api.v1.alerts.mark-fixed', $alert), [
            'fixed_location_name' => 'River bank, cleaned stretch',
            'fixed_latitude' => 35.1234,
            'fixed_longitude' => 44.5678,
            'fix_images' => [UploadedFile::fake()->image('clean-bank.jpg')],
        ], $this->bearer($helper))
            ->assertOk()
            ->assertJsonPath('data.status', 'fixed')
            ->assertJsonPath('data.fixed_location_name', 'River bank, cleaned stretch')
            ->assertJsonPath('data.fixed_latitude', 35.1234)
            ->assertJsonPath('data.fixed_longitude', 44.5678)
            ->assertJsonPath('message', 'This alert is now marked as Fixed. Thank you for taking care of it.');

        $alert->refresh();
        $this->assertSame(25, $helper->refresh()->score);
        $this->assertNotNull($alert->fixed_at);
        $this->assertSame('River bank, cleaned stretch', $alert->fixed_location_name);
        $this->assertEqualsWithDelta(35.1234, $alert->fixed_latitude, 0.0000001);
        $this->assertEqualsWithDelta(44.5678, $alert->fixed_longitude, 0.0000001);
    }

    public function test_mark_fixed_requires_a_mobile_token(): void
    {
        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->postJson(route('api.v1.alerts.mark-fixed', $alert))
            ->assertUnauthorized();

        $plain = $helper->createToken('Pixel 8', ['other'])->plainTextToken;

        $this->postJson(route('api.v1.alerts.mark-fixed', $alert), [], $this->bearerToken($plain))
            ->assertForbidden();

        $this->assertSame('in_progress', $alert->fresh()->status);
    }

    public function test_mark_fixed_requires_a_proof_photo_and_coordinates(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->post(route('api.v1.alerts.mark-fixed', $alert), [], $this->bearer($helper))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fix_images.0', 'Add a proof photo to mark this alert as fixed.')
            ->assertJsonPath('errors.fixed_latitude.0', 'Add the latitude and longitude of the place you fixed.')
            ->assertJsonPath('errors.fixed_longitude.0', 'Add the latitude and longitude of the place you fixed.');

        $this->assertSame('in_progress', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->fixed_at);
        $this->assertSame(0, $helper->fresh()->score);
        $this->assertSame(0, $alert->fixImages()->count());
    }

    public function test_mark_fixed_rejects_an_invalid_photo_or_coordinates(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->post(route('api.v1.alerts.mark-fixed', $alert), [
            'fixed_latitude' => 91,
            'fixed_longitude' => -181,
            'fix_images' => [UploadedFile::fake()->image('after.gif')],
        ], $this->bearer($helper))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fix_images.0' => 'Proof photos must be JPEG, PNG, or WebP.',
                'fixed_latitude' => 'Latitude must be between -90 and 90.',
                'fixed_longitude' => 'Longitude must be between -180 and 180.',
            ]);

        $this->post(route('api.v1.alerts.mark-fixed', $alert), [
            'fixed_latitude' => 35.1,
            'fixed_longitude' => 44.5,
            'fix_images' => [UploadedFile::fake()->image('huge.jpg')->size(5121)],
        ], $this->bearer($helper))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fix_images.0' => 'Each proof photo must be 5 MB or smaller.',
            ]);

        $this->assertSame('in_progress', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->fixed_at);
        $this->assertSame(0, $alert->fixImages()->count());
        Storage::disk('public')->assertDirectoryEmpty('alerts/fixes');
    }

    public function test_another_member_cannot_update_or_delete_an_alert_and_a_disabled_account_cannot_create_one(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $owner->id]);

        $this->patch(route('api.v1.alerts.update', $alert), $this->alertFields(), $this->bearer($other))
            ->assertForbidden();

        $this->deleteJson(route('api.v1.alerts.destroy', $alert), [], $this->bearer($other))
            ->assertForbidden();

        $disabled = User::factory()->create();
        $token = $disabled->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $disabled->forceFill(['status' => false])->save();

        $this->post(route('api.v1.alerts.store'), $this->alertFields(), $this->bearerToken($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function alertFields(string $title = 'Oil spill beside the footbridge'): array
    {
        return [
            'title' => $title,
            'description' => 'Dark oil is spreading from the drain into the stream.',
            'location_name' => 'South footbridge',
            'severity' => 'high',
            'featured_image' => UploadedFile::fake()->image('spill.jpg'),
        ];
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
