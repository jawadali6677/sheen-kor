<?php

namespace App\Http\Controllers;

use App\Actions\CreateAlert;
use App\Actions\DeleteAlert;
use App\Actions\MarkAlertFixed;
use App\Actions\TakeAlertAction;
use App\Actions\UpdateAlert;
use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->input('q', ''));

        $alerts = Alert::query()
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'actionUser',
                'reportImages',
            ])
            ->withCount([
                'likes',
                'comments' => function ($query) {
                    $query->where('status', 'approved');
                },
            ])
            ->withExists([
                'likes as liked_by_user' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ])
            ->when(in_array($status, ['open', 'in_progress', 'fixed'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('location_name', 'like', $like);
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        if ($request->boolean('partial') || $request->headers->has('X-Infinite-Scroll')) {
            return view('alerts.partials.feed-items', compact('alerts'));
        }

        return view('alerts.index', compact('alerts', 'status', 'search'));
    }

    public function create()
    {
        $this->authorize('create', Alert::class);

        return view('alerts.create');
    }

    public function store(Request $request, CreateAlert $createAlert)
    {
        $this->authorize('create', Alert::class);

        try {
            $alert = $createAlert->handle($request);
        } catch (ContentWriteFailed $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('alerts.show', $alert)
            ->with('success', 'Your environmental alert has been posted. Others can now see it.');
    }

    public function show(Alert $alert)
    {
        abort_unless($alert->isVisibleTo(auth()->user()), 404);

        $alert->load(['user', 'images', 'actionUser', 'reportImages', 'fixImages']);

        $alert->loadCount([
            'likes',
            'comments' => function ($query) {
                $query->where('status', 'approved');
            },
        ]);

        $likedByUser = $alert->isLikedBy(auth()->id());
        $likesCount = $alert->likes_count;
        $commentsCount = $alert->comments_count;

        $alert->increment('views');

        return view('alerts.show', compact(
            'alert',
            'likedByUser',
            'likesCount',
            'commentsCount'
        ));
    }

    public function edit(Alert $alert)
    {
        $this->authorize('update', $alert);

        $alert->load('reportImages');

        return view('alerts.edit', compact('alert'));
    }

    public function update(Request $request, Alert $alert, UpdateAlert $updateAlert)
    {
        $this->authorize('update', $alert);

        try {
            $alert = $updateAlert->handle($request, $alert);
        } catch (ContentWriteFailed $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('alerts.show', $alert)
            ->with('success', 'The alert has been updated.');
    }

    public function destroy(Alert $alert, DeleteAlert $deleteAlert)
    {
        $this->authorize('delete', $alert);

        try {
            $deleteAlert->handle($alert);
        } catch (ContentWriteFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('alerts.index')
            ->with('success', 'The alert has been deleted.');
    }

    /**
     * Claim an open alert so only this person/organization can fix it.
     */
    public function takeAction(Request $request, Alert $alert, TakeAlertAction $takeAlertAction)
    {
        $this->authorize('takeAction', $alert);

        try {
            $error = $takeAlertAction->handle($request->user(), $alert);
        } catch (ContentWriteFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with(
            'success',
            'You have taken this alert. Others cannot take it while you work on it. Mark it as Fixed when the cleanup is done.'
        );
    }

    /**
     * Mark a claimed alert as fixed. Only the person who took action can do this.
     */
    public function markFixed(Request $request, Alert $alert, MarkAlertFixed $markAlertFixed)
    {
        $this->authorize('markFixed', $alert);

        try {
            $markAlertFixed->handle($request, $alert);
        } catch (ContentWriteFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            'This alert is now marked as Fixed. Thank you for taking care of it.'
        );
    }
}
