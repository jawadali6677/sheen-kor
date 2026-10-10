<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use App\Models\AlertImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAlert
{
    public function __construct(private AwardScore $awardScore) {}

    public function handle(Request $request): Alert
    {
        $request->validate(array_merge([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', 'in:low,medium,high'],
            'featured_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], shortVideoRules()));

        DB::beginTransaction();

        try {
            $featuredImage = $request
                ->file('featured_image')
                ->store('alerts/featured', 'public');

            $alert = Alert::create([
                'user_id' => $request->user()->id,
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

            $sortOrder = 0;

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

            $this->awardScore->handle($request->user(), ScoreReason::AlertCreated, $alert);

            DB::commit();

            return $alert;
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while posting your alert.', previous: $exception);
        }
    }
}
