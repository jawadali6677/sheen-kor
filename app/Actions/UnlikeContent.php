<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class UnlikeContent
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(User $actor, Post|Alert $likeable): array
    {
        if ($likeable instanceof Post && $likeable->status !== 'published') {
            return [
                'payload' => [
                    'success' => false,
                    'message' => 'You can only unlike published stories.',
                ],
                'status' => 403,
            ];
        }

        $noun = $likeable instanceof Alert ? 'alert' : 'story';

        DB::beginTransaction();

        try {
            $likeable->likes()
                ->where('user_id', $actor->id)
                ->delete();

            DB::commit();

            $likesCount = $likeable->likes()->count();
            $likeable->broadcastEngagementCounts($likesCount);

            return [
                'payload' => [
                    'success' => true,
                    'liked' => false,
                    'likes_count' => $likesCount,
                    'message' => "You unliked this {$noun}.",
                ],
                'status' => 200,
            ];
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return [
                'payload' => [
                    'success' => false,
                    'message' => "Something went wrong while unliking this {$noun}.",
                ],
                'status' => 500,
            ];
        }
    }
}
