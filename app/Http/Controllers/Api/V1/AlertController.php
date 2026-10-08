<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateAlert;
use App\Actions\DeleteAlert;
use App\Actions\MarkAlertFixed;
use App\Actions\TakeAlertAction;
use App\Actions\UpdateAlert;
use App\Exceptions\ContentWriteFailed;
use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request, CreateAlert $createAlert): JsonResponse
    {
        $this->authorize('create', Alert::class);

        try {
            $alert = $createAlert->handle($request);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Your environmental alert has been posted. Others can now see it.',
            'data' => $this->alertPayload($this->loadWrittenAlert($request, $alert)),
        ], 201);
    }

    public function update(Request $request, Alert $alert, UpdateAlert $updateAlert): JsonResponse
    {
        $this->authorize('update', $alert);

        try {
            $alert = $updateAlert->handle($request, $alert);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'message' => 'The alert has been updated.',
            'data' => $this->alertPayload($this->loadWrittenAlert($request, $alert)),
        ]);
    }

    public function destroy(Alert $alert, DeleteAlert $deleteAlert): JsonResponse
    {
        $this->authorize('delete', $alert);

        try {
            $deleteAlert->handle($alert);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'message' => 'The alert has been deleted.',
        ]);
    }

    public function takeAction(Request $request, Alert $alert, TakeAlertAction $takeAlertAction): JsonResponse
    {
        $this->authorize('takeAction', $alert);

        try {
            $error = $takeAlertAction->handle($request->user(), $alert);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        if ($error !== null) {
            throw ValidationException::withMessages([
                'alert' => $error,
            ]);
        }

        return response()->json([
            'message' => 'You have taken this alert. Others cannot take it while you work on it. Mark it as Fixed when the cleanup is done.',
            'data' => $this->alertPayload($this->loadWrittenAlert($request, $alert)),
        ]);
    }

    public function markFixed(Request $request, Alert $alert, MarkAlertFixed $markAlertFixed): JsonResponse
    {
        $this->authorize('markFixed', $alert);

        try {
            $alert = $markAlertFixed->handle($request, $alert);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'message' => 'This alert is now marked as Fixed. Thank you for taking care of it.',
            'data' => $this->alertPayload($this->loadWrittenAlert($request, $alert)),
        ]);
    }

    private function loadWrittenAlert(Request $request, Alert $alert): Alert
    {
        $viewerId = $request->user()?->id;

        return $alert->refresh()->load([
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
        ])->loadCount([
            'likes',
            'comments' => function ($query): void {
                $query->where('status', 'approved');
            },
        ])->loadExists([
            'likes as liked_by_user' => function ($query) use ($viewerId): void {
                $query->where('user_id', $viewerId);
            },
        ]);
    }
}
