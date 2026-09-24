<?php

namespace App\Models;

use App\Enums\PostBoostStatus;
use App\Events\PostEngagementUpdated;
use App\Models\Concerns\HasEngagement;
use App\Models\Concerns\PresentsMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Throwable;

class Post extends Model
{
    use HasEngagement, HasFactory, PresentsMedia;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(PostImage::class)->orderBy('sort_order');
    }

    protected function galleryMedia(): Collection
    {
        return $this->images;
    }

    public function tips()
    {
        return $this->hasMany(Tip::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(PostBoost::class);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeBoosted(Builder $query): void
    {
        $query->whereHas('boosts', function (Builder $query): void {
            $query->currentlyActive();
        });
    }

    public function hasActiveBoost(): bool
    {
        if (array_key_exists('is_boosted', $this->attributes)) {
            return (bool) $this->attributes['is_boosted'];
        }

        if ($this->relationLoaded('boosts')) {
            return $this->boosts->contains(
                fn (PostBoost $boost): bool => $boost->isCurrentlyActive(),
            );
        }

        return $this->boosts()->currentlyActive()->exists();
    }

    public function currentBoost(): ?PostBoost
    {
        if ($this->relationLoaded('boosts')) {
            return $this->boosts->first(
                fn (PostBoost $boost): bool => $boost->isCurrentlyActive(),
            );
        }

        return $this->boosts()->currentlyActive()->latest('id')->first();
    }

    public function hasOpenBoost(): bool
    {
        return $this->hasActiveBoost()
            || $this->boosts()->where('status', PostBoostStatus::Pending)->exists();
    }

    public function broadcastEngagementCounts(?int $likesCount = null, ?int $commentsCount = null): void
    {
        try {
            broadcast(new PostEngagementUpdated(
                $this->id,
                $likesCount ?? $this->likes()->count(),
                $commentsCount ?? $this->comments()->where('status', 'approved')->count(),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
