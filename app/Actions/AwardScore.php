<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Models\ScoreEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AwardScore
{
    public function handle(User $user, ScoreReason $reason, Model $source): void
    {
        $points = (int) config('scoring.'.$reason->value, 0);

        if ($points <= 0) {
            return;
        }

        $event = ScoreEvent::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'reason' => $reason->value,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
            ],
            [
                'points' => $points,
            ],
        );

        if ($event->wasRecentlyCreated) {
            $user->increment('score', $points);
        }
    }
}
