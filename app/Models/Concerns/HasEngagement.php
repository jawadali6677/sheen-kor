<?php

namespace App\Models\Concerns;

use App\Models\Comment;
use App\Models\Like;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEngagement
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function isLikedBy(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return $this->likes()
            ->where('user_id', $userId)
            ->exists();
    }
}
