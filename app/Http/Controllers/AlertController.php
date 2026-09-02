<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->input('q', ''));

        $alerts = Alert::query()
            ->with('user')
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
            ->when(in_array($status, ['open', 'acknowledged', 'resolved'], true), function ($query) use ($status) {
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
        return view('alerts.create');
    }

    public function store(Request $request)
    {
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
                    ]);
                }
            }

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
        $alert->load(['user', 'images']);

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
        abort_unless($alert->user_id === auth()->id(), 403);

        $alert->load('images');

        return view('alerts.edit', compact('alert'));
    }

    public function update(Request $request, Alert $alert)
    {
        abort_unless($alert->user_id === auth()->id(), 403);

        $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:open,acknowledged,resolved'],
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
                'status' => $request->status,
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
        abort_unless($alert->user_id === auth()->id(), 403);

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
}
