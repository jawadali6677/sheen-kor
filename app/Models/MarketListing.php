<?php

namespace App\Models;

use App\Enums\ListingPromotionPlacement;
use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Enums\OrderStatus;
use App\Models\Concerns\PresentsMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class MarketListing extends Model
{
    use HasFactory, PresentsMedia;

    protected $fillable = [
        'user_id',
        'market_category_id',
        'title',
        'slug',
        'description',
        'listing_type',
        'condition',
        'price',
        'exchange_details',
        'location_name',
        'latitude',
        'longitude',
        'featured_image',
        'status',
        'published_at',
        'closed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'listing_type' => MarketListingType::class,
            'condition' => MarketListingCondition::class,
            'status' => MarketListingStatus::class,
            'price' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (MarketListing $listing): void {
            Order::query()
                ->where('market_listing_id', $listing->id)
                ->where('status', OrderStatus::Pending)
                ->get()
                ->each(function (Order $order): void {
                    $order->cancelIfPending();
                });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(MarketCategory::class, 'market_category_id');
    }

    public function images()
    {
        return $this->hasMany(MarketListingImage::class)->orderBy('sort_order');
    }

    public function reports()
    {
        return $this->hasMany(MarketListingReport::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(ListingPromotion::class);
    }

    public function latestPromotion(): HasOne
    {
        return $this->hasOne(ListingPromotion::class)->latestOfMany();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function currentOrLatestPromotion(): ?ListingPromotion
    {
        return $this->currentPromotion() ?? $this->latestPromotion;
    }

    protected function galleryMedia(): Collection
    {
        return $this->images;
    }

    /**
     * @param  Builder<MarketListing>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', MarketListingStatus::Published);
    }

    public function currentPromotion(): ?ListingPromotion
    {
        if ($this->relationLoaded('promotions')) {
            return $this->promotions->first(
                fn (ListingPromotion $promotion): bool => $promotion->isCurrentlyActive(),
            );
        }

        return $this->promotions()->currentlyActive()->latest('id')->first();
    }

    public function promotionBadge(): ?string
    {
        return $this->currentPromotion()?->placement?->badge();
    }

    public function hasActivePromotion(): bool
    {
        return $this->currentPromotion() !== null;
    }

    public function hasOpenPromotion(): bool
    {
        return $this->promotions()->currentlyActive()->exists()
            || $this->promotions()->where('status', ListingPromotionStatus::Pending)->exists();
    }

    public function ownerPromotion(): ?ListingPromotion
    {
        if ($this->relationLoaded('promotions')) {
            return $this->promotions
                ->sortByDesc('id')
                ->first(function (ListingPromotion $promotion): bool {
                    return $promotion->isCurrentlyActive()
                        || $promotion->status === ListingPromotionStatus::Pending;
                });
        }

        return $this->promotions()
            ->where(function (Builder $query): void {
                $query->currentlyActive()
                    ->orWhere('status', ListingPromotionStatus::Pending);
            })
            ->latest('id')
            ->first();
    }

    public function ownerPromotionHeadline(): ?string
    {
        return $this->ownerPromotion()?->ownerStatusHeadline();
    }

    /**
     * @param  Builder<MarketListing>  $query
     */
    public function scopeWithCatalogPromotion(Builder $query, ?int $categoryId): void
    {
        $query->with(['promotions' => function ($promotions): void {
            $promotions->currentlyActive();
        }])
            ->withExists(['promotions as is_promoted_here' => function ($promotions) use ($categoryId): void {
                $promotions->currentlyActive();

                if ($categoryId) {
                    return;
                }

                $promotions->whereIn('placement', [
                    ListingPromotionPlacement::FeaturedHome->value,
                    ListingPromotionPlacement::BoostRank->value,
                ]);
            }]);
    }
}
