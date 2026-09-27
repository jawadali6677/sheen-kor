<?php

namespace App\Http\Controllers;

use App\Actions\RecordQualifiedPostView;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualifiedPostViewController extends Controller
{
    /**
     * Record feed qualified views for posts that stayed visible.
     *
     * The client batches post ids after each card has been at least half
     * visible for two continuous seconds. Unpublished and missing posts are
     * rejected. A batch that contains no published post is a 404.
     */
    public function store(Request $request, RecordQualifiedPostView $recordQualifiedPostView): JsonResponse
    {
        $validated = $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:20'],
            'post_ids.*' => ['integer', 'distinct'],
        ]);

        $posts = Post::query()
            ->whereIn('id', $validated['post_ids'])
            ->get()
            ->keyBy('id');

        abort_unless(
            $posts->contains(fn (Post $post): bool => $post->status === 'published'),
            404,
        );

        $recorded = [];

        foreach ($validated['post_ids'] as $postId) {
            $post = $posts->get($postId);

            if ($post === null) {
                continue;
            }

            if ($recordQualifiedPostView->handle($post, $request)) {
                $recorded[] = $post->id;
            }
        }

        return response()->json([
            'recorded' => $recorded,
        ]);
    }
}
