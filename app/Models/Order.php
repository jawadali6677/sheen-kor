<?php

namespace App\Models;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\ListingPromotionPlacement;
use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'post_id',
        'market_listing_id',
        'status',
        'amount',
        'currency',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MonetizationPackage::class, 'package_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function verification(): HasOne
    {
        return $this->hasOne(UserVerification::class);
    }

    public function boost(): HasOne
    {
        return $this->hasOne(PostBoost::class);
    }

    public function listingPromotion(): HasOne
    {
        return $this->hasOne(ListingPromotion::class);
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }

    public function shouldChargeWithStripe(): bool
    {
        return stripe_checkout_is_configured()
            && $this->status === OrderStatus::Pending
            && $this->amountInCents() > 0;
    }

    public function expireStripeCheckoutSessions(): void
    {
        $this->payments()
            ->where('provider', PaymentProvider::Stripe)
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(function (Payment $payment): void {
                $sessionId = $payment->provider_reference;

                if (! is_string($sessionId) || ! str_starts_with($sessionId, 'cs_')) {
                    return;
                }

                app(StripeCheckoutGateway::class)->expireSession($sessionId);
            });
    }

    public function cancelIfPending(): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        $this->expireStripeCheckoutSessions();

        $this->payments()
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => PaymentStatus::Cancelled->value,
            ]);

        $this->forceFill([
            'status' => OrderStatus::Cancelled,
        ])->save();

        return true;
    }

    public function packageType(): ?MonetizationPackageType
    {
        $type = $this->snapshot['type'] ?? $this->package?->type;

        if ($type instanceof MonetizationPackageType) {
            return $type;
        }

        return is_string($type) ? MonetizationPackageType::tryFrom($type) : null;
    }

    public function purchaseTypeLabel(): string
    {
        return match ($this->packageType()) {
            MonetizationPackageType::GreenTick => 'Green Tick',
            MonetizationPackageType::PostBoost => 'Post boost',
            MonetizationPackageType::ListingPromotion => 'Listing promotion',
            MonetizationPackageType::RewardedBoost => 'Rewarded boost',
            default => 'Purchase',
        };
    }

    public function isListingPromotion(): bool
    {
        return $this->packageType() === MonetizationPackageType::ListingPromotion;
    }

    public function purchasedItemName(): string
    {
        return match ($this->packageType()) {
            MonetizationPackageType::ListingPromotion => $this->relatedListing()?->title ?? 'Deleted listing',
            MonetizationPackageType::PostBoost => $this->relatedPost()?->title ?? 'Deleted post',
            MonetizationPackageType::GreenTick => $this->user?->name ?? 'Profile',
            default => (string) ($this->snapshot['name'] ?? $this->package?->name ?? 'Purchase'),
        };
    }

    public function promotionPlacementLabel(): ?string
    {
        return $this->listingPromotion?->placement->label();
    }

    public function benefitStatusLabel(): string
    {
        $entitlement = $this->listingPromotion ?? $this->boost ?? $this->verification;

        if ($entitlement === null) {
            return 'None';
        }

        return $entitlement->displayStatus()->label();
    }

    public function durationDays(): ?int
    {
        $days = $this->listingPromotion?->duration_days
            ?? $this->boost?->duration_days
            ?? $this->verification?->duration_days
            ?? ($this->snapshot['duration_days'] ?? null);

        if (! is_numeric($days)) {
            return null;
        }

        return (int) $days;
    }

    public function resultHeadline(): string
    {
        $forDays = $this->forDaysPhrase();

        return match ($this->status) {
            OrderStatus::Paid => $this->paidResultHeadline($forDays),
            OrderStatus::Pending => $this->pendingResultHeadline($forDays),
            OrderStatus::Failed => 'This payment did not complete. You can start a new purchase.',
            OrderStatus::Cancelled => 'This order was cancelled.',
            OrderStatus::Refunded => 'This order was refunded.',
        };
    }

    public function resultDetail(): ?string
    {
        if ($this->listingPromotion?->isCurrentlyActive()) {
            return $this->listingPromotion->activeUntilPhrase();
        }

        if ($this->boost?->isCurrentlyActive()) {
            return $this->boost->activeUntilPhrase();
        }

        if ($this->verification?->isCurrentlyActive() && $this->verification->ends_at !== null) {
            return 'Active until '.$this->verification->ends_at->toFormattedDateString();
        }

        return null;
    }

    /**
     * @return array{url: string, label: string}|null
     */
    public function primaryExit(): ?array
    {
        $listing = $this->relatedListing();

        if ($this->isListingPromotion() && $listing !== null) {
            return [
                'url' => route('market.show', $listing),
                'label' => 'View your listing',
            ];
        }

        $post = $this->relatedPost();

        if ($this->packageType() === MonetizationPackageType::PostBoost && $post !== null) {
            return [
                'url' => route('posts.show', $post),
                'label' => 'View your post',
            ];
        }

        if ($this->packageType() === MonetizationPackageType::GreenTick && $this->user !== null) {
            return [
                'url' => route('users.show', $this->user),
                'label' => 'View your profile',
            ];
        }

        return null;
    }

    private function relatedListing(): ?MarketListing
    {
        return $this->listing ?? $this->listingPromotion?->listing;
    }

    private function relatedPost(): ?Post
    {
        return $this->post ?? $this->boost?->post;
    }

    private function forDaysPhrase(): string
    {
        $days = $this->durationDays();

        if ($days === null || $days < 1) {
            return '';
        }

        return ' for '.$days.' '.($days === 1 ? 'day' : 'days');
    }

    private function pendingResultHeadline(string $forDays): string
    {
        return match ($this->packageType()) {
            MonetizationPackageType::ListingPromotion => 'This order promotes '.$this->purchasedItemName().$forDays.' after payment.',
            MonetizationPackageType::PostBoost => 'This order boosts '.$this->purchasedItemName().$forDays.' after payment.',
            MonetizationPackageType::GreenTick => 'This order is a Green Tick'.$forDays.'. It starts after payment.',
            default => 'This order is waiting for payment.',
        };
    }

    private function paidResultHeadline(string $forDays): string
    {
        return match ($this->packageType()) {
            MonetizationPackageType::ListingPromotion => $this->paidListingHeadline($forDays),
            MonetizationPackageType::PostBoost => $this->paidBoostHeadline($forDays),
            MonetizationPackageType::GreenTick => $this->paidGreenTickHeadline($forDays),
            default => 'This purchase is paid.',
        };
    }

    private function paidListingHeadline(string $forDays): string
    {
        $promotion = $this->listingPromotion;

        if ($promotion?->isCurrentlyActive()) {
            return match ($promotion->placement) {
                ListingPromotionPlacement::FeaturedHome => 'Your listing is featured'.$forDays.'.',
                ListingPromotionPlacement::TopOfCategory => 'Your listing is at the top of its category'.$forDays.'.',
                ListingPromotionPlacement::BoostRank => 'Your listing is promoted'.$forDays.'.',
            };
        }

        if ($promotion?->displayStatus() === ListingPromotionStatus::Expired) {
            return 'Your listing promotion has ended.';
        }

        return 'Your listing promotion is paid'.$forDays.'.';
    }

    private function paidBoostHeadline(string $forDays): string
    {
        if ($this->boost?->isCurrentlyActive()) {
            return 'Your post is boosted'.$forDays.'.';
        }

        if ($this->boost?->displayStatus() === PostBoostStatus::Expired) {
            return 'Your post boost has ended.';
        }

        return 'Your post boost is paid'.$forDays.'.';
    }

    private function paidGreenTickHeadline(string $forDays): string
    {
        $verification = $this->verification;

        if ($verification?->isCurrentlyActive()) {
            return 'Your Green Tick is active'.$forDays.'.';
        }

        if ($verification?->displayStatus() === UserVerificationStatus::PendingReview) {
            return 'Your Green Tick payment is complete. We will review your profile before the badge appears.';
        }

        if ($verification?->displayStatus() === UserVerificationStatus::Rejected) {
            return 'Your Green Tick payment is complete, but the request was not approved.';
        }

        if ($verification?->displayStatus() === UserVerificationStatus::Expired) {
            return 'Your Green Tick has ended.';
        }

        return 'Your Green Tick payment is complete'.$forDays.'.';
    }
}
