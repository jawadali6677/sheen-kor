<?php

namespace App\Jobs;

use App\Actions\ModerateContent;
use App\Enums\ModerationDecision;
use App\Enums\Permission;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostModerationResult;
use App\Notifications\PostNeedsReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ModeratePostContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Stay under the database queue retry_after of 90 seconds.
     */
    public int $timeout = 75;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 20];

    public function __construct(
        public int $postId,
        public string $contentVersion,
    ) {}

    public static function contentVersion(Post $post): string
    {
        $post->loadMissing('images');

        $media = $post->images
            ->map(fn ($image): string => $image->id.'|'.$image->image.'|'.$image->sort_order)
            ->implode("\n");

        return hash('sha256', implode("\n", [
            (string) $post->title,
            (string) $post->excerpt,
            (string) $post->content,
            (string) $post->featured_image,
            $media,
        ]));
    }

    public function handle(ModerateContent $moderateContent): void
    {
        $post = $this->currentPost();

        if ($post === null) {
            return;
        }

        if ($this->isFinished($post)) {
            $this->notifyAuthor($post);

            return;
        }

        $decision = $this->decisionFor($post, $moderateContent);

        $appliedStatus = DB::transaction(function () use ($decision): ?string {
            $post = Post::query()->whereKey($this->postId)->lockForUpdate()->first();

            if ($post === null || ! $this->isCurrentVersion($post)) {
                return null;
            }

            if ($this->isFinished($post)) {
                return $post->status;
            }

            $this->applyDecision($post, $decision);

            return $post->status;
        });

        if ($appliedStatus === null) {
            return;
        }

        $post = $this->currentPost();

        if ($post === null || $post->status !== $appliedStatus) {
            return;
        }

        if ($this->isFinished($post)) {
            $this->notifyAuthor($post);

            return;
        }

        $this->notifyReviewers($post);
    }

    public function failed(?Throwable $exception): void
    {
        $post = $this->currentPost();

        if ($post === null || $this->isFinished($post)) {
            return;
        }

        $post->update([
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->notifyReviewers($post->refresh());
    }

    private function currentPost(): ?Post
    {
        $post = Post::query()->with('images')->find($this->postId);

        if ($post === null || ! $this->isCurrentVersion($post)) {
            return null;
        }

        return $post;
    }

    private function isCurrentVersion(Post $post): bool
    {
        return self::contentVersion($post) === $this->contentVersion;
    }

    private function isFinished(Post $post): bool
    {
        return $post->status === 'published' || $post->status === 'rejected';
    }

    private function decisionFor(Post $post, ModerateContent $moderateContent): ModerationDecision
    {
        $text = trim(implode("\n\n", array_filter([
            $post->title,
            $post->excerpt,
            $post->content,
        ], fn (?string $value): bool => filled($value))));

        $imagePaths = [];
        $videoPaths = [];

        if (filled($post->featured_image)) {
            $imagePaths[] = Storage::disk('public')->path($post->featured_image);
        }

        foreach ($post->images as $media) {
            $path = Storage::disk('public')->path($media->image);

            if ($media->isVideo()) {
                $videoPaths[] = $path;

                continue;
            }

            $imagePaths[] = $path;
        }

        return $moderateContent->handle($text, $imagePaths, $videoPaths);
    }

    private function applyDecision(Post $post, ModerationDecision $decision): void
    {
        if ($decision === ModerationDecision::Allow) {
            $post->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return;
        }

        if ($decision === ModerationDecision::Reject) {
            $post->update([
                'status' => 'rejected',
                'published_at' => null,
            ]);

            return;
        }

        $post->update([
            'status' => 'pending',
            'published_at' => null,
        ]);
    }

    private function notifyAuthor(Post $post): void
    {
        $author = $post->user;

        if ($author === null) {
            return;
        }

        $outcome = $post->status === 'published'
            ? PostModerationResult::OutcomePublished
            : PostModerationResult::OutcomeRejected;

        if ($post->status !== 'published' && $post->status !== 'rejected') {
            return;
        }

        $kind = $outcome === PostModerationResult::OutcomePublished ? 'post_published' : 'post_rejected';

        $alreadyNotified = $author->notifications()
            ->where('type', PostModerationResult::class)
            ->where('data->post_id', $post->id)
            ->where('data->kind', $kind)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        try {
            $author->notifyInbox(new PostModerationResult($post, $outcome));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function notifyReviewers(Post $post): void
    {
        $post->loadMissing('user');

        if ($post->status !== 'pending') {
            return;
        }

        $reviewers = User::query()->withPermission(Permission::ModeratePosts)->get();

        foreach ($reviewers as $reviewer) {
            $alreadyNotified = $reviewer->unreadNotifications()
                ->where('type', PostNeedsReview::class)
                ->where('data->post_id', $post->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $reviewer->notifyInbox(new PostNeedsReview($post));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
