<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

class ListComments
{
    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function handle(User $actor, Post|Alert $commentable): array
    {
        if ($commentable instanceof Post
            && $commentable->status !== 'published'
            && $commentable->user_id !== $actor->id
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

        return [
            'payload' => [
                'success' => true,
                'liked' => $commentable->isLikedBy($actor->id),
                'likes_count' => $commentable->likes()->count(),
                'comments_count' => $commentable->comments()->where('status', 'approved')->count(),
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
            ],
            'status' => 200,
        ];
    }
}
