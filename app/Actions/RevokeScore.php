<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Models\ScoreEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RevokeScore
{
    public function handle(User $user, ScoreReason $reason, Model $source): void
    {
        $event = ScoreEvent::query()
            ->where('user_id', $user->id)
            ->where('reason', $reason->value)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();

        if ($event === null) {
            return;
        }

        $user->forceFill([
            'score' => max(0, (int) $user->score - $event->points),
        ])->save();

        $event->delete();
    }
}
