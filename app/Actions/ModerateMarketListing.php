<?php

namespace App\Actions;

use App\Enums\MarketListingStatus;
use App\Enums\ModerationDecision;
use App\Enums\Permission;
use App\Models\MarketListing;
use App\Models\User;
use App\Notifications\MarketListingNeedsReview;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ModerateMarketListing
{
    public function __construct(private ModerateContent $moderateContent) {}

    public function handle(MarketListing $listing): void
    {
        try {
            $listing->refresh()->load('images');

            $text = trim(implode("\n\n", array_filter([
                $listing->title,
                $listing->description,
                $listing->exchange_details,
            ], fn (?string $value): bool => filled($value))));

            $imagePaths = [];

            if (filled($listing->featured_image)) {
                $imagePaths[] = Storage::disk('public')->path($listing->featured_image);
            }

            foreach ($listing->images as $media) {
                $imagePaths[] = Storage::disk('public')->path($media->image);
            }

            $decision = $this->moderateContent->handle($text, $imagePaths, []);

            if ($decision === ModerationDecision::Allow) {
                $listing->update([
                    'status' => MarketListingStatus::Published,
                    'published_at' => now(),
                ]);

                return;
            }

            if ($decision === ModerationDecision::Reject) {
                $listing->update([
                    'status' => MarketListingStatus::Rejected,
                    'published_at' => null,
                ]);

                return;
            }

            $listing->update([
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            $this->notifyReviewersIfPending($listing);
        } catch (Throwable $exception) {
            report($exception);

            $listing->update([
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            $this->notifyReviewersIfPending($listing);
        }
    }

    private function notifyReviewersIfPending(MarketListing $listing): void
    {
        $listing->refresh()->loadMissing('user');

        if ($listing->status !== MarketListingStatus::Pending) {
            return;
        }

        $reviewers = User::query()->withPermission(Permission::ModerateMarketListings)->get();

        foreach ($reviewers as $reviewer) {
            $alreadyNotified = $reviewer->unreadNotifications()
                ->where('type', MarketListingNeedsReview::class)
                ->where('data->listing_id', $listing->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $reviewer->notifyInbox(new MarketListingNeedsReview($listing));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
