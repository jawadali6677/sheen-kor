<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ContentCommented;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateComment
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(Request $request, User $actor, Post|Alert $commentable): array
    {
        $noun = $commentable instanceof Alert ? 'alert' : 'story';

        if ($commentable instanceof Post && $commentable->status !== 'published') {
            return [
                'payload' => [
                    'success' => false,
                    'message' => "You can only comment on published {$noun}s.",
                ],
                'status' => 403,
            ];
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
            'parent_id' => [
                'nullable',
                'integer',
                'exists:comments,id',
            ],
        ]);

        $threadParentId = null;

        if ($request->filled('parent_id')) {
            $parent = Comment::query()->find($request->integer('parent_id'));

            if (
                ! $parent ||
                $parent->commentable_id !== $commentable->id ||
                $parent->commentable_type !== $commentable->getMorphClass() ||
                $parent->status !== 'approved'
            ) {
                return [
                    'payload' => [
                        'success' => false,
                        'message' => "You can only reply to a comment on this {$noun}.",
                        'errors' => [
                            'parent_id' => [
                                "You can only reply to a comment on this {$noun}.",
                            ],
                        ],
                    ],
                    'status' => 422,
                ];
            }

            $threadParentId = $parent->parent_id ?: $parent->id;
        }

        DB::beginTransaction();

        try {
            $comment = $commentable->comments()->create([
                'user_id' => $actor->id,
                'parent_id' => $threadParentId,
                'content' => $request->content,
                'status' => 'approved',
            ]);

            $comment->load('user');

            DB::commit();

            $this->notifyOwner($actor, $commentable, $comment);

            $commentsCount = $commentable->comments()->where('status', 'approved')->count();
            $commentable->broadcastEngagementCounts(commentsCount: $commentsCount);

            return [
                'payload' => [
                    'success' => true,
                    'message' => $comment->parent_id
                        ? 'Your reply has been added.'
                        : 'Your comment has been added.',
                    'comment' => $comment->toEngagementPayload($actor),
                    'comments_count' => $commentsCount,
                ],
                'status' => 201,
            ];
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return [
                'payload' => [
                    'success' => false,
                    'message' => 'Something went wrong while posting your comment.',
                ],
                'status' => 500,
            ];
        }
    }

    private function notifyOwner(User $actor, Post|Alert $commentable, Comment $comment): void
    {
        $owner = $commentable->user;

        if (! $owner || $owner->id === $actor->id) {
            return;
        }

        try {
            $owner->notifyInbox(new ContentCommented($actor, $commentable, $comment));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
