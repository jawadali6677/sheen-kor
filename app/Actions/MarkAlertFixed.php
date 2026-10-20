<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use App\Models\AlertImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MarkAlertFixed
{
    public function __construct(private AwardScore $awardScore) {}

    public function handle(Request $request, Alert $alert): Alert
    {
        $validated = $request->validate(array_merge([
            'fixed_location_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'fixed_latitude' => ['required', 'numeric', 'between:-90,90'],
            'fixed_longitude' => ['required', 'numeric', 'between:-180,180'],
            'fix_images' => ['required', 'array', 'min:1', 'max:10'],
            'fix_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], shortVideoRules('fix_videos')), [
            'fix_images.required' => 'Add a proof photo to mark this alert as fixed.',
            'fix_images.min' => 'Add a proof photo to mark this alert as fixed.',
            'fix_images.*.image' => 'Proof photos must be JPEG, PNG, or WebP.',
            'fix_images.*.mimes' => 'Proof photos must be JPEG, PNG, or WebP.',
            'fix_images.*.max' => 'Each proof photo must be 5 MB or smaller.',
            'fixed_latitude.required' => 'Add the latitude and longitude of the place you fixed.',
            'fixed_longitude.required' => 'Add the latitude and longitude of the place you fixed.',
            'fixed_latitude.numeric' => 'Latitude must be a number between -90 and 90.',
            'fixed_longitude.numeric' => 'Longitude must be a number between -180 and 180.',
            'fixed_latitude.between' => 'Latitude must be between -90 and 90.',
            'fixed_longitude.between' => 'Longitude must be between -180 and 180.',
        ]);

        $storedPaths = [];

        DB::beginTransaction();

        try {
            $locked = Alert::query()->whereKey($alert->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress() || ! $request->user()?->can('markFixed', $locked)) {
                throw ValidationException::withMessages([
                    'alert' => $locked->isFixed()
                        ? 'This alert is already fixed.'
                        : 'This alert can only be marked fixed while it is in progress.',
                ]);
            }

            $sortOrder = $locked->fixImages()->count();

            foreach ($request->file('fix_images', []) as $image) {
                $path = $image->store('alerts/fixes', 'public');
                $storedPaths[] = $path;

                AlertImage::create([
                    'alert_id' => $locked->id,
                    'image' => $path,
                    'caption' => null,
                    'sort_order' => $sortOrder,
                    'kind' => 'fix',
                    'media_type' => 'image',
                ]);

                $sortOrder++;
            }

            foreach ($request->file('fix_videos', []) as $video) {
                $path = $video->store('alerts/fixes', 'public');
                $storedPaths[] = $path;

                AlertImage::create([
                    'alert_id' => $locked->id,
                    'image' => $path,
                    'caption' => null,
                    'sort_order' => $sortOrder,
                    'kind' => 'fix',
                    'media_type' => 'video',
                ]);

                $sortOrder++;
            }

            $locked->update([
                'status' => 'fixed',
                'fixed_at' => now(),
                'fixed_location_name' => $validated['fixed_location_name'] ?? null,
                'fixed_latitude' => $validated['fixed_latitude'],
                'fixed_longitude' => $validated['fixed_longitude'],
            ]);

            $this->awardScore->handle($request->user(), ScoreReason::AlertFixed, $locked);

            DB::commit();

            return $locked->refresh();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($storedPaths !== []) {
                Storage::disk('public')->delete($storedPaths);
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while marking this alert as fixed.', previous: $exception);
        }
    }
}
