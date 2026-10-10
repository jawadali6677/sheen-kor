<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeleteComment
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(Comment $comment): array
    {
        $commentable = $comment->commentable;

        DB::beginTransaction();

        try {
            $commentableType = $comment->commentable_type;
            $commentableId = $comment->commentable_id;

            $comment->delete();

            DB::commit();

            $commentsCount = Comment::query()
                ->where('commentable_type', $commentableType)
                ->where('commentable_id', $commentableId)
                ->where('status', 'approved')
                ->count();

            if ($commentable instanceof Post || $commentable instanceof Alert) {
                $commentable->broadcastEngagementCounts(commentsCount: $commentsCount);
            }

            return [
                'payload' => [
                    'success' => true,
                    'message' => 'The comment has been deleted.',
                    'comments_count' => $commentsCount,
                ],
                'status' => 200,
            ];
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return [
                'payload' => [
                    'success' => false,
                    'message' => 'Something went wrong while deleting the comment.',
                ],
                'status' => 500,
            ];
        }
    }
}
