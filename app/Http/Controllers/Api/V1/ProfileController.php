<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateProfile;
use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProfileController extends Controller
{
    use SerializesApiContent;

    public function show(Request $request, User $user): JsonResponse
    {
        $profile = $this->visibleProfile($request, $user);

        $profile->loadExists([
            'greenTickVerifications as has_active_green_tick' => function ($query): void {
                $query->currentlyActive();
            },
        ]);

        $profile->loadCount([
            'posts as stories_count' => function ($query): void {
                $query->where('status', 'published');
            },
            'alerts',
            'claimedAlerts as fixes_count' => function ($query): void {
                $query->where('status', 'fixed');
            },
            'marketListings as listings_count' => function ($query): void {
                $query->where('status', 'published');
            },
        ]);

        return response()->json([
            'data' => $this->profilePayload($profile, $request->user()),
        ]);
    }

    public function update(Request $request, UpdateProfile $updateProfile): JsonResponse
    {
        UpdateProfile::prepare($request);

        $user = $updateProfile->handle(
            $request->user(),
            $request->validate(UpdateProfile::rules($request->user())),
            $request,
        );

        return response()->json([
            'message' => 'Profile updated.',
            'data' => $this->accountPayload($user),
        ]);
    }

    public function stories(Request $request, User $user): JsonResponse
    {
        $profile = $this->visibleProfile($request, $user);
        $viewerId = $request->user()?->id;

        $posts = $profile->posts()
            ->where('status', 'published')
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'category',
                'images',
            ])
            ->withCount([
                'likes',
                'comments' => function ($query): void {
                    $query->where('status', 'approved');
                },
            ])
            ->withExists([
                'likes as liked_by_user' => function ($query) use ($viewerId): void {
                    $query->where('user_id', $viewerId);
                },
                'boosts as is_boosted' => function ($query): void {
                    $query->currentlyActive();
                },
            ])
            ->latest('published_at')
            ->latest('id')
            ->paginate(18);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (Post $post): array => $this->postPayload($post))->values(),
            'meta' => $this->paginationMeta($posts),
        ]);
    }

    public function alerts(Request $request, User $user): JsonResponse
    {
        $profile = $this->visibleProfile($request, $user);

        $alerts = $this->alertPage(
            $profile->alerts()->latest('created_at')->latest('id'),
            $request,
        );

        return response()->json([
            'data' => $alerts['data'],
            'meta' => $alerts['meta'],
        ]);
    }

    public function fixes(Request $request, User $user): JsonResponse
    {
        $profile = $this->visibleProfile($request, $user);

        $alerts = $this->alertPage(
            $profile->claimedAlerts()
                ->whereIn('status', ['in_progress', 'fixed'])
                ->latest('action_taken_at')
                ->latest('id'),
            $request,
        );

        return response()->json([
            'data' => $alerts['data'],
            'meta' => $alerts['meta'],
        ]);
    }

    public function listings(Request $request, User $user): JsonResponse
    {
        $profile = $this->visibleProfile($request, $user);

        $listings = $profile->marketListings()
            ->where('status', 'published')
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'category',
                'images',
            ])
            ->with(['promotions' => function ($query): void {
                $query->currentlyActive();
            }])
            ->latest('published_at')
            ->latest('id')
            ->paginate(18);

        return response()->json([
            'data' => $listings->getCollection()->map(fn (MarketListing $listing): array => $this->listingPayload($listing))->values(),
            'meta' => $this->paginationMeta($listings),
        ]);
    }

    private function visibleProfile(Request $request, User $user): User
    {
        abort_unless($user->status || $request->user()?->id === $user->id, 404);

        return $user;
    }

    /**
     * @param  HasMany<Alert, User>  $query
     * @return array{data: Collection<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    private function alertPage($query, Request $request): array
    {
        $viewerId = $request->user()?->id;

        $alerts = $query
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'actionUser' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'reportImages',
            ])
            ->withCount([
                'likes',
                'comments' => function ($query): void {
                    $query->where('status', 'approved');
                },
            ])
            ->withExists([
                'likes as liked_by_user' => function ($query) use ($viewerId): void {
                    $query->where('user_id', $viewerId);
                },
            ])
            ->paginate(18);

        return [
            'data' => $alerts->getCollection()->map(fn (Alert $alert): array => $this->alertPayload($alert))->values(),
            'meta' => $this->paginationMeta($alerts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user, ?User $viewer): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'score' => (int) $user->score,
            'has_green_tick' => $user->hasActiveGreenTick(),
            'avatar_url' => $user->avatarUrl(),
            'cover_url' => $user->coverUrl(),
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => $user->website,
            'is_following' => $viewer instanceof User && $viewer->isFollowing($user),
            'stories_count' => (int) $user->stories_count,
            'alerts_count' => (int) $user->alerts_count,
            'fixes_count' => (int) $user->fixes_count,
            'listings_count' => (int) $user->listings_count,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'username' => $user->username,
            'role' => $user->role,
            'score' => (int) $user->score,
            'has_green_tick' => $user->hasActiveGreenTick(),
            'avatar_url' => $user->avatarUrl(),
            'cover_url' => $user->coverUrl(),
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => $user->website,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
