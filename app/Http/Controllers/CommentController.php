<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommentController extends Controller
{
    public function indexPost(Post $post): JsonResponse
    {
        return $this->indexFor($post);
    }

    public function storePost(Request $request, Post $post): JsonResponse
    {
        return $this->storeFor($request, $post);
    }

    public function indexAlert(Alert $alert): JsonResponse
    {
        return $this->indexFor($alert);
    }

    public function storeAlert(Request $request, Alert $alert): JsonResponse
    {
        return $this->storeFor($request, $alert);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $commentable = $comment->commentable;

        if (! $commentable || $this->engagementDenied($commentable)) {
            return response()->json([
                'success' => false,
                'message' => 'This comment can no longer be edited.',
            ], 403);
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

            return response()->json([
                'success' => true,
                'message' => 'Your comment has been updated.',
                'comment' => $comment->toEngagementPayload(auth()->user()),
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating your comment.',
            ], 500);
        }
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

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

            return response()->json([
                'success' => true,
                'message' => 'The comment has been deleted.',
                'comments_count' => $commentsCount,
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while deleting the comment.',
            ], 500);
        }
    }

    private function indexFor(Model $commentable): JsonResponse
    {
        if ($commentable instanceof Post
            && $commentable->status !== 'published'
            && $commentable->user_id !== auth()->id()
        ) {
            abort(404);
        }

        $comments = $commentable->comments()
            ->whereNull('parent_id')
            ->where('status', 'approved')
            ->with([
                'user',
                'replies' => function ($query) {
                    $query->where('status', 'approved')
                        ->with('user')
                        ->orderBy('created_at');
                },
            ])
            ->latest()
            ->get();

        $actor = auth()->user();

        return response()->json([
            'success' => true,
            'liked' => $commentable->isLikedBy($actor?->id),
            'likes_count' => $commentable->likes()->count(),
            'comments_count' => $this->approvedCommentsCount($commentable),
            'item' => [
                'id' => $commentable->id,
                'title' => $commentable->title,
            ],
            'comments' => $comments->map(function (Comment $comment) use ($actor) {
                $payload = $comment->toEngagementPayload($actor);
                $payload['replies'] = $comment->replies
                    ->map(fn (Comment $reply) => $reply->toEngagementPayload($actor))
                    ->values();

                return $payload;
            })->values(),
        ]);
    }

    private function storeFor(Request $request, Model $commentable): JsonResponse
    {
        $noun = $commentable instanceof Alert ? 'alert' : 'story';

        if ($this->engagementDenied($commentable)) {
            return response()->json([
                'success' => false,
                'message' => "You can only comment on published {$noun}s.",
            ], 403);
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

        if ($request->filled('parent_id')) {
            $parent = Comment::query()->find($request->integer('parent_id'));

            if (
                ! $parent ||
                $parent->commentable_id !== $commentable->id ||
                $parent->commentable_type !== $commentable->getMorphClass() ||
                $parent->status !== 'approved' ||
                $parent->parent_id !== null
            ) {
                return response()->json([
                    'success' => false,
                    'message' => "You can only reply to a top-level comment on this {$noun}.",
                    'errors' => [
                        'parent_id' => [
                            "You can only reply to a top-level comment on this {$noun}.",
                        ],
                    ],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {

            $comment = $commentable->comments()->create([
                'user_id' => auth()->id(),
                'parent_id' => $request->input('parent_id'),
                'content' => $request->content,
                'status' => 'approved',
            ]);

            $comment->load('user');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $comment->parent_id
                    ? 'Your reply has been added.'
                    : 'Your comment has been added.',
                'comment' => $comment->toEngagementPayload(auth()->user()),
                'comments_count' => $this->approvedCommentsCount($commentable),
            ], 201);

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while posting your comment.',
            ], 500);
        }
    }

    private function engagementDenied(Model $commentable): bool
    {
        return $commentable instanceof Post && $commentable->status !== 'published';
    }

    private function approvedCommentsCount(Model $commentable): int
    {
        return $commentable->comments()
            ->where('status', 'approved')
            ->count();
    }
}
