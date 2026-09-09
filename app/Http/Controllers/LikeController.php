<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Post;
use App\Notifications\ContentLiked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class LikeController extends Controller
{
    public function storePost(Post $post): JsonResponse
    {
        return $this->storeFor($post);
    }

    public function destroyPost(Post $post): JsonResponse
    {
        return $this->destroyFor($post);
    }

    public function storeAlert(Alert $alert): JsonResponse
    {
        return $this->storeFor($alert);
    }

    public function destroyAlert(Alert $alert): JsonResponse
    {
        return $this->destroyFor($alert);
    }

    private function storeFor(Model $likeable): JsonResponse
    {
        $denied = $this->engagementDenied($likeable, 'like');

        if ($denied) {
            return $denied;
        }

        $noun = $this->noun($likeable);

        DB::beginTransaction();

        try {

            $existingLike = $likeable->likes()
                ->where('user_id', auth()->id())
                ->lockForUpdate()
                ->first();

            if (! $existingLike) {
                $likeable->likes()->create([
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            if (! $existingLike) {
                $this->notifyOwnerOfLike($likeable);
            }

            $likesCount = $likeable->likes()->count();

            if ($likeable instanceof Post || $likeable instanceof Alert) {
                $likeable->broadcastEngagementCounts($likesCount);
            }

            return response()->json([
                'success' => true,
                'liked' => true,
                'likes_count' => $likesCount,
                'message' => $existingLike
                    ? "You already liked this {$noun}."
                    : "You liked this {$noun}.",
            ]);

        } catch (UniqueConstraintViolationException $e) {

            DB::rollBack();

            $likesCount = $likeable->likes()->count();

            if ($likeable instanceof Post || $likeable instanceof Alert) {
                $likeable->broadcastEngagementCounts($likesCount);
            }

            return response()->json([
                'success' => true,
                'liked' => true,
                'likes_count' => $likesCount,
                'message' => "You liked this {$noun}.",
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => "Something went wrong while liking this {$noun}.",
            ], 500);
        }
    }

    private function destroyFor(Model $likeable): JsonResponse
    {
        $denied = $this->engagementDenied($likeable, 'unlike');

        if ($denied) {
            return $denied;
        }

        $noun = $this->noun($likeable);

        DB::beginTransaction();

        try {

            $likeable->likes()
                ->where('user_id', auth()->id())
                ->delete();

            DB::commit();

            $likesCount = $likeable->likes()->count();

            if ($likeable instanceof Post || $likeable instanceof Alert) {
                $likeable->broadcastEngagementCounts($likesCount);
            }

            return response()->json([
                'success' => true,
                'liked' => false,
                'likes_count' => $likesCount,
                'message' => "You unliked this {$noun}.",
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => "Something went wrong while unliking this {$noun}.",
            ], 500);
        }
    }

    private function engagementDenied(Model $likeable, string $action): ?JsonResponse
    {
        if ($likeable instanceof Post && $likeable->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => "You can only {$action} published stories.",
            ], 403);
        }

        return null;
    }

    private function noun(Model $likeable): string
    {
        return $likeable instanceof Alert ? 'alert' : 'story';
    }

    private function notifyOwnerOfLike(Model $likeable): void
    {
        $owner = $likeable->user;

        if (! $owner || $owner->id === auth()->id()) {
            return;
        }

        try {
            $owner->notifyInbox(new ContentLiked(auth()->user(), $likeable));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
