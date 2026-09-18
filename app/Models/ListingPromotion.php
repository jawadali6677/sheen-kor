<?php

namespace App\Models;

use App\Enums\ListingPromotionPlacement;
use App\Enums\ListingPromotionSource;
use App\Enums\ListingPromotionStatus;
use Database\Factories\ListingPromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPromotion extends Model
{
    /** @use HasFactory<ListingPromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'market_listing_id',
        'package_id',
        'order_id',
        'status',
        'source',
        'placement',
        'package_type',
        'package_name',
        'package_slug',
        'duration_days',
        'price',
        'currency',
        'starts_at',
        'ends_at',
        'activated_by',
        'activated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingPromotionStatus::class,
            'source' => ListingPromotionSource::class,
            'placement' => ListingPromotionPlacement::class,
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MonetizationPackage::class, 'package_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * @param  Builder<ListingPromotion>  $query
     */
    public function scopeCurrentlyActive(Builder $query): void
    {
        $query->where('status', ListingPromotionStatus::Active)
            ->where('ends_at', '>', now());
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === ListingPromotionStatus::Active
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function displayStatus(): ListingPromotionStatus
    {
        if ($this->status === ListingPromotionStatus::Active && $this->ends_at?->isPast()) {
            return ListingPromotionStatus::Expired;
        }

        return $this->status;
    }

    public function activateFromSnapshot(?User $activator = null): void
    {
        $startsAt = now();

        $this->forceFill([
            'status' => ListingPromotionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($this->duration_days),
            'activated_by' => $activator?->id,
            'activated_at' => $startsAt,
        ])->save();
    }
}
