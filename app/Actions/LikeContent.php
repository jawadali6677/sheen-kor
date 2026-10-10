<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ContentLiked;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class LikeContent
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(User $actor, Post|Alert $likeable): array
    {
        $denied = $this->engagementDenied($likeable, 'like');

        if ($denied !== null) {
            return $denied;
        }

        $noun = $this->noun($likeable);

        DB::beginTransaction();

        try {
            $existingLike = $likeable->likes()
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $existingLike) {
                $likeable->likes()->create([
                    'user_id' => $actor->id,
                ]);
            }

            DB::commit();

            if (! $existingLike) {
                $this->notifyOwner($actor, $likeable);
            }

            $likesCount = $likeable->likes()->count();
            $likeable->broadcastEngagementCounts($likesCount);

            return $this->payload(
                true,
                $likesCount,
                $existingLike
                    ? "You already liked this {$noun}."
                    : "You liked this {$noun}.",
            );
        } catch (UniqueConstraintViolationException) {
            DB::rollBack();

            $likesCount = $likeable->likes()->count();
            $likeable->broadcastEngagementCounts($likesCount);

            return $this->payload(true, $likesCount, "You liked this {$noun}.");
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return [
                'payload' => [
                    'success' => false,
                    'message' => "Something went wrong while liking this {$noun}.",
                ],
                'status' => 500,
            ];
        }
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}|null
     */
    private function engagementDenied(Post|Alert $likeable, string $action): ?array
    {
        if ($likeable instanceof Post && $likeable->status !== 'published') {
            return [
                'payload' => [
                    'success' => false,
                    'message' => "You can only {$action} published stories.",
                ],
                'status' => 403,
            ];
        }

        return null;
    }

    private function noun(Post|Alert $likeable): string
    {
        return $likeable instanceof Alert ? 'alert' : 'story';
    }

    private function notifyOwner(User $actor, Post|Alert $likeable): void
    {
        $owner = $likeable->user;

        if (! $owner || $owner->id === $actor->id) {
            return;
        }

        try {
            $owner->notifyInbox(new ContentLiked($actor, $likeable));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    private function payload(bool $liked, int $likesCount, string $message): array
    {
        return [
            'payload' => [
                'success' => true,
                'liked' => $liked,
                'likes_count' => $likesCount,
                'message' => $message,
            ],
            'status' => 200,
        ];
    }
}
