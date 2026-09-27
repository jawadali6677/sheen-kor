<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecordQualifiedPostView
{
    /**
     * Record at most one qualified view per logged-in viewer and post per rolling 24 hours.
     *
     * Feed cards qualify in the browser (at least 50% visible for 2 continuous
     * seconds) and call the beacon. Opening the post page qualifies here on
     * the same counter, so a feed view and a page open inside 24 hours count
     * once. Guests, the author, unpublished posts, and obvious bots are
     * skipped. This does not change the raw posts.views counter.
     *
     * The window is rolling: a later view counts only when the previous row's
     * created_at is at least 24 hours old. Concurrent requests for the same
     * viewer and post take a cache lock (the default store; database and array
     * both support it) and re-check that window before inserting.
     */
    public function handle(Post $post, Request $request): bool
    {
        $viewer = $request->user();

        if (
            ! $viewer instanceof User
            || $post->status !== 'published'
            || (int) $viewer->id === (int) $post->user_id
            || $this->isObviousBot($request)
        ) {
            return false;
        }

        try {
            return $this->record($post, $viewer);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function record(Post $post, User $viewer): bool
    {
        return Cache::lock('qualified-post-view:'.$post->id.':'.$viewer->id, 10)
            ->block(5, function () use ($post, $viewer): bool {
                $viewedWithinWindow = PostView::query()
                    ->where('post_id', $post->id)
                    ->where('viewer_user_id', $viewer->id)
                    ->where('created_at', '>', now()->subHours(24))
                    ->exists();

                if ($viewedWithinWindow) {
                    return false;
                }

                $timestamp = now();

                PostView::query()->insert([
                    'post_id' => $post->id,
                    'user_id' => $post->user_id,
                    'viewer_user_id' => $viewer->id,
                    'viewer_key' => 'user:'.$viewer->id,
                    'viewed_on' => $timestamp->toDateString(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                return true;
            });
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
