<?php

namespace App\Console\Commands;

use App\Enums\ListingPromotionStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\ListingPromotion;
use App\Models\PostBoost;
use App\Models\UserVerification;
use App\Notifications\GreenTickExpired;
use App\Notifications\ListingPromotionExpired;
use App\Notifications\PostBoostExpired;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monetization:expire-entitlements')]
#[Description('Mark ended Green Ticks, post boosts, and listing promotions as expired.')]
class ExpireMonetizationEntitlements extends Command
{
    public function handle(): int
    {
        $expiredTicks = 0;
        $expiredBoosts = 0;
        $expiredPromotions = 0;

        UserVerification::query()
            ->where('status', UserVerificationStatus::Active)
            ->where('ends_at', '<=', now())
            ->with('user')
            ->each(function (UserVerification $verification) use (&$expiredTicks): void {
                $verification->forceFill([
                    'status' => UserVerificationStatus::Expired,
                ])->save();

                $verification->user?->notify(new GreenTickExpired($verification->fresh()));
                $expiredTicks++;
            });

        PostBoost::query()
            ->where('status', PostBoostStatus::Active)
            ->where('ends_at', '<=', now())
            ->with(['user', 'post'])
            ->each(function (PostBoost $boost) use (&$expiredBoosts): void {
                $boost->forceFill([
                    'status' => PostBoostStatus::Expired,
                ])->save();

                $boost->user?->notify(new PostBoostExpired($boost->fresh(['post'])));
                $expiredBoosts++;
            });

        ListingPromotion::query()
            ->where('status', ListingPromotionStatus::Active)
            ->where('ends_at', '<=', now())
            ->with(['user', 'listing'])
            ->each(function (ListingPromotion $promotion) use (&$expiredPromotions): void {
                $promotion->forceFill([
                    'status' => ListingPromotionStatus::Expired,
                ])->save();

                $promotion->user?->notify(new ListingPromotionExpired($promotion->fresh(['listing'])));
                $expiredPromotions++;
            });

        $this->info("Expired {$expiredTicks} Green Ticks, {$expiredBoosts} post boosts, and {$expiredPromotions} listing promotions.");

        return self::SUCCESS;
    }
}
