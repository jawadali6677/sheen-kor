<?php

namespace Tests\Feature;

use App\Enums\ListingPromotionStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\ListingPromotion;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\GreenTickExpired;
use App\Notifications\ListingPromotionExpired;
use App\Notifications\PostBoostExpired;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpireMonetizationEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_expires_ended_active_entitlements_and_notifies_owners_once(): void
    {
        $this->freezeTime();
        Notification::fake();

        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);

        $tick = UserVerification::factory()->active()->create([
            'user_id' => $user->id,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subMinute(),
        ]);
        $boost = PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subMinute(),
        ]);
        $promotion = ListingPromotion::factory()->active()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
            'starts_at' => now()->subDays(8),
            'ends_at' => now()->subMinute(),
        ]);

        $this->artisan('monetization:expire-entitlements')
            ->assertSuccessful();

        $this->assertSame(UserVerificationStatus::Expired, $tick->fresh()->status);
        $this->assertSame(PostBoostStatus::Expired, $boost->fresh()->status);
        $this->assertSame(ListingPromotionStatus::Expired, $promotion->fresh()->status);
        $this->assertFalse($user->fresh()->hasActiveGreenTick());
        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertFalse($listing->fresh()->hasActivePromotion());

        Notification::assertSentTo($user, GreenTickExpired::class);
        Notification::assertSentTo($user, PostBoostExpired::class);
        Notification::assertSentTo($user, ListingPromotionExpired::class);
        Notification::assertCount(3);

        $this->artisan('monetization:expire-entitlements')
            ->assertSuccessful();

        Notification::assertCount(3);
    }

    public function test_future_and_pending_entitlements_are_not_expired(): void
    {
        $this->freezeTime();
        Notification::fake();

        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $futureTick = UserVerification::factory()->active()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDay(),
        ]);
        $pendingTick = UserVerification::factory()->create([
            'user_id' => $user->id,
            'status' => UserVerificationStatus::PendingPayment,
        ]);
        $pendingBoost = PostBoost::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'status' => PostBoostStatus::Pending,
            'ends_at' => now()->subDay(),
        ]);

        $this->artisan('monetization:expire-entitlements')
            ->assertSuccessful();

        $this->assertSame(UserVerificationStatus::Active, $futureTick->fresh()->status);
        $this->assertSame(UserVerificationStatus::PendingPayment, $pendingTick->fresh()->status);
        $this->assertSame(PostBoostStatus::Pending, $pendingBoost->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_the_expiry_command_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($scheduledEvent): bool => str_contains((string) $scheduledEvent->command, 'monetization:expire-entitlements'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
