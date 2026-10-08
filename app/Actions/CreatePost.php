<?php

namespace App\Actions;

use App\Actions\Concerns\BuildsPostContent;
use App\Enums\ScoreReason;
use App\Exceptions\ContentWriteFailed;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePost
{
    use BuildsPostContent;

    public function __construct(private AwardScore $awardScore) {}

    public function handle(Request $request): Post
    {
        $request->validate($this->postFieldRules());
        $this->assertPostHasTextOrMedia($request, null);

        [$hasPhoto, $hasVideo] = $this->mediaPresence($request, null);
        $resolved = $this->resolvedTitle($request, null, $hasPhoto, $hasVideo);

        DB::beginTransaction();

        try {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'category_id' => $this->resolvedCategoryId($request),
                'title' => $resolved['title'],
                'slug' => $this->uniquePostSlug($resolved['title']),
                'excerpt' => $this->resolvedExcerpt($request, null),
                'content' => $this->resolvedContent($request),
                'title_is_generated' => $resolved['generated'],
                'status' => 'pending',
                'published_at' => null,
                'views' => 0,
            ]);

            $this->attachUploadedMedia($request, $post);

            $this->awardScore->handle($request->user(), ScoreReason::PostCreated, $post);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while creating your post.', previous: $exception);
        }

        $this->queueContentModeration($post);

        return $post;
    }
}
