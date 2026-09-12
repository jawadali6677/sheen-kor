<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RevokeScore;
use App\Enums\ScoreReason;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Notifications\PostModerationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PostController extends Controller
{
    public function __construct(
        private RevokeScore $revokeScore,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('moderate', Post::class);

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'published', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $search = trim((string) $request->input('q', ''));

        $posts = Post::query()
            ->with('user')
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content', 'like', $like)
                        ->orWhereHas('user', function ($query) use ($like) {
                            $query->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'posts' => $posts,
            'status' => $status,
            'search' => $search,
            'counts' => $this->statusCounts(),
        ];

        if ($request->boolean('partial') || $request->headers->has('X-Infinite-Scroll')) {
            return view('admin.posts.partials.results', $data);
        }

        return view('admin.posts.index', $data);
    }

    public function show(Post $post): View
    {
        $this->authorize('moderate', Post::class);

        $post->load(['user', 'category', 'images']);

        return view('admin.posts.show', compact('post'));
    }

    public function publish(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $post, 'published');
    }

    public function pending(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $post, 'pending');
    }

    public function reject(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $post, 'rejected');
    }

    public function destroy(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('moderate', Post::class);

        DB::beginTransaction();

        try {
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }

            foreach ($post->images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }

            $post->likes()->delete();
            $post->comments()->delete();

            if ($post->user) {
                $this->revokeScore->handle($post->user, ScoreReason::PostCreated, $post);
            }

            $post->delete();

            DB::commit();

            return $this->moderationResponse($request, 'The story has been deleted.');
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return $this->moderationResponse(
                $request,
                'Something went wrong while deleting this story.',
                error: true,
            );
        }
    }

    private function changeStatus(Request $request, Post $post, string $status): RedirectResponse|JsonResponse
    {
        $this->authorize('moderate', Post::class);

        $wasPending = $post->status === 'pending';

        $post->update([
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        if ($wasPending && in_array($status, ['published', 'rejected'], true)) {
            $this->notifyOwnerOfModerationResult($post, $status);
        }

        $message = match ($status) {
            'published' => 'The story has been published.',
            'rejected' => 'The story has been rejected.',
            default => 'The story is pending review.',
        };

        return $this->moderationResponse($request, $message);
    }

    private function moderationResponse(Request $request, string $message, bool $error = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => ! $error,
                'message' => $message,
                'counts' => $this->statusCounts(),
            ], $error ? 500 : 200);
        }

        if ($error) {
            return back()->with('error', $message);
        }

        if ($request->routeIs('admin.posts.destroy')) {
            return redirect()
                ->route('admin.posts.index')
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * @return array{pending: int, published: int, rejected: int, total: int}
     */
    private function statusCounts(): array
    {
        return [
            'pending' => Post::query()->where('status', 'pending')->count(),
            'published' => Post::query()->where('status', 'published')->count(),
            'rejected' => Post::query()->where('status', 'rejected')->count(),
            'total' => Post::query()->count(),
        ];
    }

    private function notifyOwnerOfModerationResult(Post $post, string $status): void
    {
        $owner = $post->user;

        if (! $owner || $owner->id === auth()->id()) {
            return;
        }

        $outcome = $status === 'published'
            ? PostModerationResult::OutcomePublished
            : PostModerationResult::OutcomeRejected;

        try {
            $owner->notifyInbox(new PostModerationResult($post, $outcome));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
