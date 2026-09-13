<?php

namespace App\Models;

use App\Models\Concerns\HasMediaFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketListingImage extends Model
{
    use HasFactory, HasMediaFile;

    protected $fillable = [
        'market_listing_id',
        'image',
        'caption',
        'sort_order',
        'media_type',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'media_type' => 'image',
    ];

    protected static function booted(): void
    {
        static::saving(function (MarketListingImage $image): void {
            $image->media_type = 'image';
        });
    }

    public function listing()
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }
}
