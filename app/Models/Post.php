<?php

namespace App\Models;

use App\Events\PostEngagementUpdated;
use App\Models\Concerns\HasEngagement;
use App\Models\Concerns\PresentsMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
