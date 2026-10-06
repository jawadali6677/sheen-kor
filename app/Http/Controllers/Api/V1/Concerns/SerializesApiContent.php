<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Alert;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait SerializesApiContent
{
    /**
     * @return array<string, mixed>
     */
    protected function postPayload(Post $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'title_is_generated' => $post->usesGeneratedTitle(),
            'status' => $post->status,
            'views' => (int) $post->views,
            'is_boosted' => $post->hasActiveBoost(),
            'liked_by_user' => (bool) $post->getAttribute('liked_by_user'),
            'likes_count' => (int) $post->likes_count,
            'comments_count' => (int) $post->comments_count,
            'featured_image_url' => $this->publicStorageUrl($post->featured_image),
            'media' => $post->mediaSlides(),
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'category' => $post->category === null ? null : [
                'id' => $post->category->id,
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ],
            'author' => $this->memberPayload($post->user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function alertPayload(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'slug' => $alert->slug,
            'title' => $alert->title,
            'description' => $alert->description,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'location_name' => $alert->location_name,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'views' => (int) $alert->views,
            'liked_by_user' => (bool) $alert->getAttribute('liked_by_user'),
            'likes_count' => (int) $alert->likes_count,
            'comments_count' => (int) $alert->comments_count,
            'featured_image_url' => $this->publicStorageUrl($alert->featured_image),
            'media' => $alert->mediaSlides(),
            'action_taken_at' => $alert->action_taken_at?->toIso8601String(),
            'fixed_at' => $alert->fixed_at?->toIso8601String(),
            'created_at' => $alert->created_at?->toIso8601String(),
            'author' => $this->memberPayload($alert->user),
            'action_user' => $alert->actionUser === null ? null : $this->memberPayload($alert->actionUser),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function listingPayload(MarketListing $listing, ?bool $reportedByUser = null): array
    {
        $payload = [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => $listing->description,
            'listing_type' => $listing->listing_type->value,
            'condition' => $listing->condition->value,
            'price' => $listing->price,
            'exchange_details' => $listing->exchange_details,
            'location_name' => $listing->location_name,
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'status' => $listing->status->value,
            'is_promoted' => (bool) ($listing->getAttribute('is_promoted_here') ?? $listing->hasActivePromotion()),
            'promotion_badge' => $listing->promotionBadge(),
            'featured_image_url' => $this->publicStorageUrl($listing->featured_image),
            'media' => $listing->mediaSlides(),
            'published_at' => $listing->published_at?->toIso8601String(),
            'created_at' => $listing->created_at?->toIso8601String(),
            'category' => $listing->category === null ? null : [
                'id' => $listing->category->id,
                'name' => $listing->category->name,
                'slug' => $listing->category->slug,
            ],
            'seller' => $this->memberPayload($listing->user),
        ];

        if ($reportedByUser !== null) {
            $payload['reported_by_user'] = $reportedByUser;
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, username: string, avatar_url: string|null, has_green_tick: bool}
     */
    protected function memberPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatarUrl(),
            'has_green_tick' => $user->hasActiveGreenTick(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    protected function publicStorageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
