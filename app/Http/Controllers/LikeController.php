<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class LikeController extends Controller
{
    /**
     * Like a published post.
     */
    public function store(Post $post): JsonResponse
    {
        $denied = $this->publishedPostOrError($post, 'like');

        if ($denied) {
            return $denied;
        }

        DB::beginTransaction();

        try {

            $existingLike = Like::query()
                ->where('user_id', auth()->id())
                ->where('post_id', $post->id)
                ->lockForUpdate()
                ->first();

            if (! $existingLike) {
                Like::create([
                    'user_id' => auth()->id(),
                    'post_id' => $post->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'liked' => true,
                'likes_count' => $post->likes()->count(),
                'message' => $existingLike
                    ? 'You already liked this story.'
                    : 'You liked this story.',
            ]);

        } catch (UniqueConstraintViolationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => true,
                'liked' => true,
                'likes_count' => $post->likes()->count(),
                'message' => 'You liked this story.',
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while liking this story.',
            ], 500);
        }
    }

    /**
     * Unlike a published post.
     */
    public function destroy(Post $post): JsonResponse
    {
        $denied = $this->publishedPostOrError($post, 'unlike');

        if ($denied) {
            return $denied;
        }

        DB::beginTransaction();

        try {

            Like::query()
                ->where('user_id', auth()->id())
                ->where('post_id', $post->id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'liked' => false,
                'likes_count' => $post->likes()->count(),
                'message' => 'You unliked this story.',
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while unliking this story.',
            ], 500);
        }
    }

    private function publishedPostOrError(Post $post, string $action): ?JsonResponse
    {
        if ($post->status === 'published') {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => "You can only {$action} published stories.",
        ], 403);
    }
}
