<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommentController extends Controller
{
    /**
     * List comments for a post (modal feed).
     */
    public function index(Post $post): JsonResponse
    {
        if (
            $post->status !== 'published' &&
            $post->user_id !== auth()->id()
        ) {
            abort(404);
        }

        $comments = $post->comments()
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

        $userId = auth()->id();
        $postOwnerId = $post->user_id;

        return response()->json([
            'success' => true,
            'liked' => $post->isLikedBy($userId),
            'likes_count' => $post->likes()->count(),
            'comments_count' => $this->approvedCommentsCount($post),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
            ],
            'comments' => $comments->map(function (Comment $comment) use ($userId, $postOwnerId) {
                $payload = $comment->toEngagementPayload($userId, $postOwnerId);
                $payload['replies'] = $comment->replies
                    ->map(fn (Comment $reply) => $reply->toEngagementPayload($userId, $postOwnerId))
                    ->values();

                return $payload;
            })->values(),
        ]);
    }

    /**
     * Store a new comment or reply.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        if ($post->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'You can only comment on published stories.',
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
                $parent->post_id !== $post->id ||
                $parent->status !== 'approved' ||
                $parent->parent_id !== null
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reply to a top-level comment on this story.',
                    'errors' => [
                        'parent_id' => [
                            'You can only reply to a top-level comment on this story.',
                        ],
                    ],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {

            $comment = Comment::create([
                'user_id' => auth()->id(),
                'post_id' => $post->id,
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
                'comment' => $comment->toEngagementPayload(
                    auth()->id(),
                    $post->user_id
                ),
                'comments_count' => $this->approvedCommentsCount($post),
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

    /**
     * Update an existing comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        abort_unless(
            $comment->user_id === auth()->id(),
            403
        );

        $post = $comment->post;

        if (! $post || $post->status !== 'published') {
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
                'comment' => $comment->toEngagementPayload(
                    auth()->id(),
                    $post->user_id
                ),
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

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $post = $comment->post;

        $canDelete = $comment->user_id === auth()->id()
            || ($post && $post->user_id === auth()->id());

        abort_unless($canDelete, 403);

        DB::beginTransaction();

        try {

            $postId = $comment->post_id;

            $comment->delete();

            DB::commit();

            $commentsCount = Comment::query()
                ->where('post_id', $postId)
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

    private function approvedCommentsCount(Post $post): int
    {
        return $post->comments()
            ->where('status', 'approved')
            ->count();
    }
}
