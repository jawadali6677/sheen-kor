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

class MarkAlertFixed
{
    public function __construct(private AwardScore $awardScore) {}

    public function handle(Request $request, Alert $alert): Alert
    {
        $request->validate(array_merge([
            'fixed_location_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'fixed_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'fixed_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'fix_images' => ['nullable', 'array', 'max:10'],
            'fix_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], shortVideoRules('fix_videos')));

        DB::beginTransaction();

        try {
            $alert->update([
                'status' => 'fixed',
                'fixed_at' => now(),
                'fixed_location_name' => $request->fixed_location_name,
                'fixed_latitude' => $request->fixed_latitude,
                'fixed_longitude' => $request->fixed_longitude,
            ]);

            $sortOrder = $alert->fixImages()->count();

            if ($request->hasFile('fix_images')) {
                foreach ($request->file('fix_images') as $image) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $image->store('alerts/fixes', 'public'),
                        'caption' => null,
                        'sort_order' => $sortOrder,
                        'kind' => 'fix',
                        'media_type' => 'image',
                    ]);

                    $sortOrder++;
                }
            }

            if ($request->hasFile('fix_videos')) {
                foreach ($request->file('fix_videos') as $video) {
                    AlertImage::create([
                        'alert_id' => $alert->id,
                        'image' => $video->store('alerts/fixes', 'public'),
                        'caption' => null,
                        'sort_order' => $sortOrder,
                        'kind' => 'fix',
                        'media_type' => 'video',
                    ]);

                    $sortOrder++;
                }
            }

            $this->awardScore->handle($request->user(), ScoreReason::AlertFixed, $alert);

            DB::commit();

            return $alert;
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while marking this alert as fixed.', previous: $exception);
        }
    }
}
