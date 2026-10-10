<?php

namespace App\Actions;

use App\Actions\Concerns\BuildsPostContent;
use App\Exceptions\ContentWriteFailed;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdatePost
{
    use BuildsPostContent;

    public function handle(Request $request, Post $post): Post
    {
        $request->validate($this->postFieldRules());
        $this->assertPostHasTextOrMedia($request, $post);

        [$hasPhoto, $hasVideo] = $this->mediaPresence($request, $post);
        $resolved = $this->resolvedTitle($request, $post, $hasPhoto, $hasVideo);

        DB::beginTransaction();

        try {
            $post->update([
                'category_id' => $this->resolvedCategoryId($request),
                'title' => $resolved['title'],
                'slug' => $this->uniquePostSlug($resolved['title'], $post->id),
                'excerpt' => $this->resolvedExcerpt($request, $post),
                'content' => $this->resolvedContent($request),
                'title_is_generated' => $resolved['generated'],
                'status' => 'pending',
                'published_at' => null,
            ]);

            $this->deleteRequestedMedia($request, $post);
            $this->attachUploadedMedia($request, $post);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while updating your post.', previous: $exception);
        }

        $this->queueContentModeration($post);

        return $post;
    }
}
