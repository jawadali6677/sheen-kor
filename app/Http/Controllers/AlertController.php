<?php

namespace App\Http\Controllers;

use App\Actions\AwardScore;
use App\Actions\RevokeScore;
use App\Enums\ScoreReason;
use App\Models\Alert;
use App\Models\AlertImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AlertController extends Controller
{
    public function __construct(
        private AwardScore $awardScore,
        private RevokeScore $revokeScore,
    ) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->input('q', ''));

        $alerts = Alert::query()
            ->with(['user', 'actionUser'])
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

        return view('alerts.index', compact('alerts', 'status', 'search'));
    }

    public function create()
    {
        $this->authorize('create', Alert::class);

        return view('alerts.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Alert::class);

        $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', 'in:low,medium,high'],
            'featured_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::beginTransaction();

        try {

            $featuredImage = $request
                ->file('featured_image')
                ->store('alerts/featured', 'public');

            $alert = Alert::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'slug' => generateUniqueSlug(Alert::class, $request->title),
                'description' => $request->description,
                'location_name' => $request->location_name,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'featured_image' => $featuredImage,
                'severity' => $request->severity,
                'status' => 'open',
                'views' => 0,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $key => $image) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $image->store('alerts/images', 'public'),
                        'caption' => null,
                        'sort_order' => $key,
                        'kind' => 'report',
                    ]);
                }
            }

            $this->awardScore->handle($request->user(), ScoreReason::AlertCreated, $alert);

            DB::commit();

            return redirect()
                ->route('alerts.show', $alert)
                ->with('success', 'Your environmental alert has been posted. Others can now see it.');

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with('error', 'Something went wrong while posting your alert.');
        }
    }

    public function show(Alert $alert)
    {
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

        $alert->load('images');

        return view('alerts.edit', compact('alert'));
    }

    public function update(Request $request, Alert $alert)
    {
        $this->authorize('update', $alert);

        $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', 'in:low,medium,high'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::beginTransaction();

        try {

            $alert->update([
                'title' => $request->title,
                'slug' => generateUniqueSlug(Alert::class, $request->title, $alert->id),
                'description' => $request->description,
                'location_name' => $request->location_name,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'severity' => $request->severity,
            ]);

            if ($request->hasFile('featured_image')) {
                $oldFeaturedImage = $alert->featured_image;

                $alert->update([
                    'featured_image' => $request
                        ->file('featured_image')
                        ->store('alerts/featured', 'public'),
                ]);

                if ($oldFeaturedImage) {
                    Storage::disk('public')->delete($oldFeaturedImage);
                }
            }

            if ($request->hasFile('images')) {
                $currentImageCount = $alert->images()->count();

                foreach ($request->file('images') as $key => $image) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $image->store('alerts/images', 'public'),
                        'caption' => null,
                        'sort_order' => $currentImageCount + $key,
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('alerts.show', $alert)
                ->with('success', 'The alert has been updated.');

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with('error', 'Something went wrong while updating the alert.');
        }
    }

    public function destroy(Alert $alert)
    {
        $this->authorize('delete', $alert);

        DB::beginTransaction();

        try {

            if ($alert->featured_image) {
                Storage::disk('public')->delete($alert->featured_image);
            }

            foreach ($alert->images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }

            $alert->likes()->delete();
            $alert->comments()->delete();

            if ($alert->user) {
                $this->revokeScore->handle($alert->user, ScoreReason::AlertCreated, $alert);
            }

            if ($alert->actionUser) {
                $this->revokeScore->handle($alert->actionUser, ScoreReason::AlertFixed, $alert);
            }

            $alert->delete();

            DB::commit();

            return redirect()
                ->route('alerts.index')
                ->with('success', 'The alert has been deleted.');

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()->with('error', 'Something went wrong while deleting the alert.');
        }
    }

    /**
     * Claim an open alert so only this person/organization can fix it.
     */
    public function takeAction(Alert $alert)
    {
        $this->authorize('takeAction', $alert);

        DB::beginTransaction();

        try {

            $lockedAlert = Alert::query()
                ->whereKey($alert->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAlert->canBeClaimedBy(auth()->id())) {
                DB::rollBack();

                return back()->with(
                    'error',
                    $lockedAlert->isFixed()
                        ? 'This alert is already fixed.'
                        : 'This alert is already being handled by someone else.'
                );
            }

            $lockedAlert->update([
                'action_user_id' => auth()->id(),
                'status' => 'in_progress',
                'action_taken_at' => now(),
            ]);

            DB::commit();

            return back()->with(
                'success',
                'You have taken this alert. Others cannot take it while you work on it. Mark it as Fixed when the cleanup is done.'
            );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()->with(
                'error',
                'Something went wrong while taking this alert.'
            );
        }
    }

    /**
     * Mark a claimed alert as fixed. Only the person who took action can do this.
     */
    public function markFixed(Request $request, Alert $alert)
    {
        $this->authorize('markFixed', $alert);

        $request->validate([
            'fixed_location_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'fixed_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'fixed_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'fix_images' => ['nullable', 'array', 'max:10'],
            'fix_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::beginTransaction();

        try {

            $alert->update([
                'status' => 'fixed',
                'fixed_at' => now(),
                'fixed_location_name' => $request->fixed_location_name,
                'fixed_latitude' => $request->fixed_latitude,
                'fixed_longitude' => $request->fixed_longitude,
            ]);

            if ($request->hasFile('fix_images')) {
                foreach ($request->file('fix_images') as $key => $image) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $image->store('alerts/fixes', 'public'),
                        'caption' => null,
                        'sort_order' => $key,
                        'kind' => 'report',
                        'kind' => 'fix',
                    ]);
                }
            }

            $this->awardScore->handle($request->user(), ScoreReason::AlertFixed, $alert);

            DB::commit();

            return back()->with(
                'success',
                'This alert is now marked as Fixed. Thank you for taking care of it.'
            );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()->with(
                'error',
                'Something went wrong while marking this alert as fixed.'
            );
        }
    }
}
