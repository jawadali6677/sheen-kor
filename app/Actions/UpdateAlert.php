<?php

namespace App\Actions;

use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use App\Models\AlertImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateAlert
{
    public function handle(Request $request, Alert $alert): Alert
    {
        $request->validate(array_merge([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', 'in:low,medium,high'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], shortVideoRules()));

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

            $sortOrder = $alert->images()->count();

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $image->store('alerts/images', 'public'),
                        'caption' => null,
                        'sort_order' => $sortOrder,
                        'kind' => 'report',
                        'media_type' => 'image',
                    ]);

                    $sortOrder++;
                }
            }

            if ($request->hasFile('videos')) {
                foreach ($request->file('videos') as $video) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $video->store('alerts/videos', 'public'),
                        'caption' => null,
                        'sort_order' => $sortOrder,
                        'kind' => 'report',
                        'media_type' => 'video',
                    ]);

                    $sortOrder++;
                }
            }

            DB::commit();

            return $alert;
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while updating the alert.', previous: $exception);
        }
    }
}
