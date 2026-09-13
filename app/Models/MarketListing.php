<?php

namespace App\Models;

use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Models\Concerns\PresentsMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
