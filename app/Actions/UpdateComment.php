<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateComment
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(Request $request, User $actor, Comment $comment): array
    {
        $commentable = $comment->commentable;

        if ($commentable === null || ($commentable instanceof Post && $commentable->status !== 'published')) {
            return $this->locked();
        }

        $request->merge([
            'content' => trim(strip_tags((string) $request->input('content'))),
        ]);

        $request->validate([
            'content' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ]);

        DB::beginTransaction();

        try {
            $comment->update([
                'content' => $request->content,
            ]);

            $comment->load('user');

            DB::commit();

            return [
                'payload' => [
                    'success' => true,
                    'message' => 'Your comment has been updated.',
                    'comment' => $comment->toEngagementPayload($actor),
                ],
                'status' => 200,
            ];
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return [
                'payload' => [
                    'success' => false,
                    'message' => 'Something went wrong while updating your comment.',
                ],
                'status' => 500,
            ];
        }
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    private function locked(): array
    {
        return [
            'payload' => [
                'success' => false,
                'message' => 'This comment can no longer be edited.',
            ],
            'status' => 403,
        ];
    }
}
