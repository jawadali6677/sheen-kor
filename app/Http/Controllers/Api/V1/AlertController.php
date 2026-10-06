<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    use SerializesApiContent;

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());
        $viewerId = $request->user()?->id;

        $alerts = Alert::query()
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
            ->when(in_array($status, ['open', 'in_progress', 'fixed'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('location_name', 'like', $like);
                });
            })
            ->latest('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $alerts->getCollection()->map(fn (Alert $alert): array => $this->alertPayload($alert))->values(),
            'meta' => $this->paginationMeta($alerts),
        ]);
    }

    public function show(Request $request, Alert $alert): JsonResponse
    {
        abort_unless($alert->isVisibleTo($request->user()), 404);

        $viewerId = $request->user()?->id;

        $alert->load([
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
            ->loadCount([
                'likes',
                'comments' => function ($query): void {
                    $query->where('status', 'approved');
                },
            ])
            ->loadExists([
                'likes as liked_by_user' => function ($query) use ($viewerId): void {
                    $query->where('user_id', $viewerId);
                },
            ]);

        $alert->increment('views');

        return response()->json([
            'data' => $this->alertPayload($alert),
        ]);
    }
}
