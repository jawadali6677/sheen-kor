<?php

namespace App\Actions;

use App\Exceptions\ContentWriteFailed;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class TakeAlertAction
{
    /**
     * Claim an open alert. Returns the same error text the website flashes when the claim is lost.
     */
    public function handle(User $user, Alert $alert): ?string
    {
        DB::beginTransaction();

        try {
            $lockedAlert = Alert::query()
                ->whereKey($alert->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAlert->canBeClaimedBy($user->id)) {
                DB::rollBack();

                return $lockedAlert->isFixed()
                    ? 'This alert is already fixed.'
                    : 'This alert is already being handled by someone else.';
            }

            $lockedAlert->update([
                'action_user_id' => $user->id,
                'status' => 'in_progress',
                'action_taken_at' => now(),
            ]);

            DB::commit();

            $alert->refresh();

            return null;
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            throw new ContentWriteFailed('Something went wrong while taking this alert.', previous: $exception);
        }
    }
}
