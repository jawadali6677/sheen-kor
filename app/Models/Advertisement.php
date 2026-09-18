<?php

namespace App\Models;

use Database\Factories\AdvertisementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertisement extends Model
{
    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'advertiser',
        'title',
        'description',
        'cta',
        'image_url',
        'destination_url',
        'is_feed',
        'is_sidebar',
        'is_video',
        'is_rewarded',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_feed' => 'boolean',
            'is_sidebar' => 'boolean',
            'is_video' => 'boolean',
            'is_rewarded' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    /**
     * @param  Builder<Advertisement>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     advertiser: string,
     *     title: string,
     *     description: string,
     *     cta: string,
     *     image: ?string,
     *     url: string,
     *     impression_url: string,
     *     placement: string
     * }
     */
    public function toCard(string $placement): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'advertiser' => $this->advertiser,
            'title' => $this->title,
            'description' => $this->description,
            'cta' => $this->cta,
            'image' => $this->image_url,
            'url' => route('ads.click', ['advertisement' => $this, 'placement' => $placement]),
            'impression_url' => route('ads.impressions.store', $this),
            'placement' => $placement,
        ];
    }
}
