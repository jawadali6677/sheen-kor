<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteAlert
{
    public function __construct(private RevokeScore $revokeScore) {}

    public function handle(Alert $alert): void
    {
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
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            throw new ContentWriteFailed('Something went wrong while deleting the alert.', previous: $exception);
        }
    }
}
