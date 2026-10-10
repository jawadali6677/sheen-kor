<?php

namespace App\Actions;

use App\Enums\ScoreReason;
use App\Exceptions\ContentWriteFailed;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeletePost
{
    public function __construct(private RevokeScore $revokeScore) {}

    public function handle(Post $post): void
    {
        DB::beginTransaction();

        try {
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }

            foreach ($post->images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }

            $post->likes()->delete();
            $post->comments()->delete();

            if ($post->user) {
                $this->revokeScore->handle($post->user, ScoreReason::PostCreated, $post);
            }

            $post->delete();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            throw new ContentWriteFailed('Something went wrong while deleting your post.', previous: $exception);
        }
    }
}
