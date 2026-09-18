<?php

namespace App\Policies;

use App\Enums\MarketListingStatus;
use App\Enums\Permission;
use App\Models\MarketListing;
use App\Models\User;

class MarketListingPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, MarketListing $listing): bool
    {
        if ($listing->status->isPubliclyVisible()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $listing->user_id === $user->id
            || $user->hasPermission(Permission::ModerateMarketListings);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateMarketListings);
    }

    public function update(User $user, MarketListing $listing): bool
    {
        if (! $listing->status->isEditable()) {
            return false;
        }

        return $listing->user_id === $user->id
            || $user->hasPermission(Permission::ModerateMarketListings);
    }

    public function delete(User $user, MarketListing $listing): bool
    {
        return $listing->user_id === $user->id
            || $user->hasPermission(Permission::ModerateMarketListings);
    }

    public function contact(User $user, MarketListing $listing): bool
    {
        if ($listing->status !== MarketListingStatus::Published) {
            return false;
        }

        if ($listing->user_id === $user->id) {
            return false;
        }

        return (bool) $listing->user?->status;
    }

    public function report(User $user, MarketListing $listing): bool
    {
        if (! $listing->status->isPubliclyVisible()) {
            return false;
        }

        return $listing->user_id !== $user->id;
    }

    public function markSold(User $user, MarketListing $listing): bool
    {
        return $this->ownerCanComplete($user, $listing)
            && $listing->listing_type->canBeMarkedSold();
    }

    public function markExchanged(User $user, MarketListing $listing): bool
    {
        return $this->ownerCanComplete($user, $listing)
            && $listing->listing_type->canBeMarkedExchanged();
    }

    public function markDonated(User $user, MarketListing $listing): bool
    {
        return $this->ownerCanComplete($user, $listing)
            && $listing->listing_type->canBeMarkedDonated();
    }

    public function close(User $user, MarketListing $listing): bool
    {
        return $this->ownerCanComplete($user, $listing);
    }

    public function moderate(User $user): bool
    {
        return $user->hasPermission(Permission::ModerateMarketListings);
    }

    public function promote(User $user, MarketListing $listing): bool
    {
        return $listing->user_id === $user->id
            && $listing->status === MarketListingStatus::Published;
    }

    private function ownerCanComplete(User $user, MarketListing $listing): bool
    {
        return $listing->user_id === $user->id
            && $listing->status === MarketListingStatus::Published;
    }
}
