<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Http\Request;
use Throwable;

class RecordQualifiedPostView
{
    /**
     * Record at most one qualified view per viewer, post, and calendar day.
     *
     * Signed-in viewers are keyed by user id, so a refresh, a new session, or
     * an IP change cannot add another view. Guests are keyed by a hash of the
     * request IP, so clearing cookies does not add another view and the raw IP
     * is not stored. The post author and obvious bots are skipped. This does
     * not change the raw posts.views counter.
     */
    public function handle(Post $post, Request $request): void
    {
        if ($post->status !== 'published' || $this->isObviousBot($request)) {
            return;
        }

        $viewer = $request->user();

        if ($viewer !== null && (int) $viewer->id === (int) $post->user_id) {
            return;
        }

        try {
            PostView::query()->insertOrIgnore([
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'viewer_key' => $this->viewerKey($request),
                'viewed_on' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function viewerKey(Request $request): string
    {
        $viewer = $request->user();

        if ($viewer !== null) {
            return 'user:'.$viewer->id;
        }

        return 'guest:'.hash('sha256', (string) $request->ip());
    }

    private function isObviousBot(Request $request): bool
    {
        $userAgent = trim((string) $request->userAgent());

        if ($userAgent === '') {
            return true;
        }

        return preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent) === 1;
    }
}
