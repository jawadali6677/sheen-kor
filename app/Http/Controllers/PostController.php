<?php

namespace App\Http\Controllers;

use App\Actions\CreatePost;
use App\Actions\DeletePost;
use App\Actions\RecordQualifiedPostView;
use App\Actions\UpdatePost;
use App\Enums\PostBoostStatus;
use App\Exceptions\ContentWriteFailed;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Display stories feed.
     */
    public function index(Request $request)
    {
        $category = null;

        if ($request->filled('category')) {
            $category = Category::query()
                ->where('status', true)
                ->where('slug', $request->string('category'))
                ->first();
        }

        return $this->renderFeed(
            category: $category,
            search: $this->feedSearchTerm($request),
        );
    }

    /**
     * Display published stories in a category.
     */
    public function byCategory(Request $request, Category $category)
    {
        abort_unless($category->status, 404);

        return $this->renderFeed(
            category: $category,
            search: $this->feedSearchTerm($request),
        );
    }

    /**
     * Display published stories by an author.
     */
    public function byAuthor(Request $request, User $user)
    {
        return $this->renderFeed(
            author: $user,
            search: $this->feedSearchTerm($request),
        );
    }

    /**
     * Show create post form.
     */
    public function create()
    {
        $this->authorize('create', Post::class);
        $categories = Category::where('status', true)
            ->orderBy('name')
            ->get();

        return view('posts.create', compact('categories'));
    }

    /**
     * Store a new post.
     */
    public function store(Request $request, CreatePost $createPost): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Post::class);

        try {
            $post = $createPost->handle($request);
        } catch (ContentWriteFailed $exception) {
            return $this->postFailureResponse($request, $exception->getMessage());
        }

        return $this->postSavedResponse($request, $post, created: true);
    }

    /**
     * Display a single post.
     */
    public function show(Request $request, Post $post, RecordQualifiedPostView $recordQualifiedPostView)
    {
        /*
        |--------------------------------------------------------------------------
        | Only published posts are publicly visible
        |--------------------------------------------------------------------------
        */

        abort_unless($post->isVisibleTo($request->user()), 404);

        /*
        |--------------------------------------------------------------------------
        | Load Relationships
        |--------------------------------------------------------------------------
        */

        $post->load([
            'user' => function ($query): void {
                $query->withExists([
                    'greenTickVerifications as has_active_green_tick' => function ($query): void {
                        $query->currentlyActive();
                    },
                ]);
            },
            'category',
            'images',
            'boosts' => function ($query): void {
                $query->with('order')->where(function ($query): void {
                    $query->currentlyActive()
                        ->orWhere('status', PostBoostStatus::Pending);
                })->latest('id');
            },
        ])
            ->loadExists([
                'boosts as is_boosted' => function ($query): void {
                    $query->currentlyActive();
                },
            ]);

        $post->loadCount([
            'likes',
            'comments' => function ($query) {
                $query->where('status', 'approved');
            },
        ]);

        $likedByUser = $post->isLikedBy(auth()->id());
        $likesCount = $post->likes_count;
        $commentsCount = $post->comments_count;

        /*
        |--------------------------------------------------------------------------
        | Increase Views
        |--------------------------------------------------------------------------
        */

        $post->increment('views');

        $recordQualifiedPostView->handle($post, $request);

        return view(
            'posts.show',
            compact(
                'post',
                'likedByUser',
                'likesCount',
                'commentsCount'
            )
        );
    }

    /**
     * Shared stories listing for feed, category, and author pages.
     */
    private function renderFeed(?Category $category = null, ?User $author = null, ?string $search = null)
    {
        $posts = Post::query()
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'category',
                'images',
            ])
            ->withCount([
                'likes',
                'comments' => function ($query) {
                    $query->where('status', 'approved');
                },
            ])
            ->withExists([
                'likes as liked_by_user' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
                'boosts as is_boosted' => function ($query): void {
                    $query->currentlyActive();
                },
            ])
            ->when($category, function ($query) use ($category) {
                $query->where('category_id', $category->id)
                    ->where('status', 'published');
            })
            ->when($author, function ($query) use ($author) {
                $query->where('user_id', $author->id)
                    ->where('status', 'published');
            })
            ->when($search, function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->where('status', 'published')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        if (request()->boolean('partial') || request()->headers->has('X-Infinite-Scroll')) {
            return view('posts.partials.feed-items', compact('posts'));
        }

        return view('posts.index', compact(
            'posts',
            'categories',
            'category',
            'author',
            'search'
        ));
    }

    private function feedSearchTerm(Request $request): ?string
    {
        $search = trim((string) $request->input('q', ''));

        return $search === '' ? null : $search;
    }

    /**
     * Show edit form.
     */
    public function edit(Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        $this->authorize('update', $post);

        $categories = Category::where('status', true)
            ->orderBy('name')
            ->get();

        $post->load('images');

        return view(
            'posts.edit',
            compact('post', 'categories')
        );
    }

    /**
     * Update post.
     */
    public function update(Request $request, Post $post, UpdatePost $updatePost): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $post);

        try {
            $post = $updatePost->handle($request, $post);
        } catch (ContentWriteFailed $exception) {
            return $this->postFailureResponse($request, $exception->getMessage());
        }

        return $this->postSavedResponse($request, $post, created: false);
    }

    /**
     * Delete post.
     */
    public function destroy(Post $post, DeletePost $deletePost)
    {
        $this->authorize('delete', $post);

        try {
            $deletePost->handle($post);
        } catch (ContentWriteFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('posts.index')
            ->with('success', 'Your post has been deleted.');
    }

    public function moderationStatus(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()?->id === $post->user_id, 403);

        return response()->json($this->moderationStatusPayload($post));
    }

    /**
     * @return array{message: string, status: string, html: ?string}
     */
    private function moderationStatusPayload(Post $post): array
    {
        $post->refresh();
        $message = $post->moderationMessage();

        return [
            'message' => $message,
            'status' => $post->status,
            'html' => $post->status === 'published'
                ? view('components.post-card', [
                    'post' => $this->prepareFeedPost($post),
                ])->render()
                : null,
        ];
    }

    private function prepareFeedPost(Post $post): Post
    {
        $post->refresh();

        $post->load([
            'user' => function ($query): void {
                $query->withExists([
                    'greenTickVerifications as has_active_green_tick' => function ($query): void {
                        $query->currentlyActive();
                    },
                ]);
            },
            'category',
            'images',
        ]);

        $post->loadCount([
            'likes',
            'comments' => function ($query) {
                $query->where('status', 'approved');
            },
        ]);

        $post->loadExists([
            'likes as liked_by_user' => function ($query) {
                $query->where('user_id', auth()->id());
            },
            'boosts as is_boosted' => function ($query): void {
                $query->currentlyActive();
            },
        ]);

        return $post;
    }

    private function postSavedResponse(Request $request, Post $post, bool $created): RedirectResponse|JsonResponse
    {
        $payload = $this->moderationStatusPayload($post);

        if ($post->status === 'pending') {
            $request->session()->flash('checking_post_id', $post->id);
        }

        if ($request->expectsJson()) {
            $request->session()->flash('success', $payload['message']);

            return response()->json([
                ...$payload,
                'status_url' => $post->status === 'pending'
                    ? route('posts.moderation-status', $post)
                    : null,
                'redirect' => route('posts.index'),
            ]);
        }

        return redirect()
            ->route('posts.index')
            ->with('success', $payload['message']);
    }

    private function postFailureResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 500);
        }

        return back()
            ->withInput()
            ->with('error', $message);
    }
}
