<?php

namespace App\Http\Controllers;

use App\Actions\AwardScore;
use App\Actions\RecordQualifiedPostView;
use App\Actions\RevokeScore;
use App\Enums\PostBoostStatus;
use App\Enums\ScoreReason;
use App\Jobs\ModeratePostContent;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PostController extends Controller
{
    public function __construct(
        private AwardScore $awardScore,
        private RevokeScore $revokeScore,
    ) {}

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
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Post::class);

        $request->validate($this->postFieldRules());
        $this->assertPostHasTextOrMedia($request, null);

        [$hasPhoto, $hasVideo] = $this->mediaPresence($request, null);
        $resolved = $this->resolvedTitle($request, null, $hasPhoto, $hasVideo);

        DB::beginTransaction();

        try {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'category_id' => $this->resolvedCategoryId($request),
                'title' => $resolved['title'],
                'slug' => $this->uniquePostSlug($resolved['title']),
                'excerpt' => $this->resolvedExcerpt($request, null),
                'content' => $this->resolvedContent($request),
                'title_is_generated' => $resolved['generated'],
                'status' => 'pending',
                'published_at' => null,
                'views' => 0,
            ]);

            $this->attachUploadedMedia($request, $post);

            $this->awardScore->handle($request->user(), ScoreReason::PostCreated, $post);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return $this->postFailureResponse(
                $request,
                'Something went wrong while creating your post.',
            );
        }

        $this->queueContentModeration($post);

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

        if (
            $post->status !== 'published' &&
            $post->user_id !== auth()->id()
        ) {
            abort(404);
        }

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
    public function update(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $post);

        $request->validate($this->postFieldRules());
        $this->assertPostHasTextOrMedia($request, $post);

        [$hasPhoto, $hasVideo] = $this->mediaPresence($request, $post);
        $resolved = $this->resolvedTitle($request, $post, $hasPhoto, $hasVideo);

        DB::beginTransaction();

        try {
            $post->update([
                'category_id' => $this->resolvedCategoryId($request),
                'title' => $resolved['title'],
                'slug' => $this->uniquePostSlug($resolved['title'], $post->id),
                'excerpt' => $this->resolvedExcerpt($request, $post),
                'content' => $this->resolvedContent($request),
                'title_is_generated' => $resolved['generated'],
                'status' => 'pending',
                'published_at' => null,
            ]);

            $this->deleteRequestedMedia($request, $post);
            $this->attachUploadedMedia($request, $post);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return $this->postFailureResponse(
                $request,
                'Something went wrong while updating your post.',
            );
        }

        $this->queueContentModeration($post);

        return $this->postSavedResponse($request, $post, created: false);
    }

    /**
     * Delete post.
     */
    public function destroy(Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        $this->authorize('delete', $post);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Delete Featured Image
            |--------------------------------------------------------------------------
            */

            if ($post->featured_image) {

                Storage::disk('public')
                    ->delete($post->featured_image);
            }

            /*
            |--------------------------------------------------------------------------
            | Delete Additional Images
            |--------------------------------------------------------------------------
            */

            foreach ($post->images as $image) {

                Storage::disk('public')
                    ->delete($image->image);

                $image->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Delete Post
            |--------------------------------------------------------------------------
            */

            $post->likes()->delete();
            $post->comments()->delete();

            if ($post->user) {
                $this->revokeScore->handle($post->user, ScoreReason::PostCreated, $post);
            }

            $post->delete();

            DB::commit();

            return redirect()
                ->route('posts.index')
                ->with(
                    'success',
                    'Your post has been deleted.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->with(
                    'error',
                    'Something went wrong while deleting your post.'
                );
        }
    }

    public function moderationStatus(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()?->id === $post->user_id, 403);

        return response()->json($this->moderationStatusPayload($post));
    }

    private function queueContentModeration(Post $post): void
    {
        $post->refresh()->load('images');

        ModeratePostContent::dispatch(
            $post->id,
            ModeratePostContent::contentVersion($post),
        )->afterCommit();
    }

    private function moderationFlashMessage(Post $post): string
    {
        $post->refresh();

        return match ($post->status) {
            'published' => 'Your post is live!',
            'rejected' => 'Your post was not published. It did not follow our community rules.',
            default => 'Your post is being checked. It will appear shortly.',
        };
    }

    /**
     * @return array{message: string, status: string, html: ?string}
     */
    private function moderationStatusPayload(Post $post): array
    {
        $message = $this->moderationFlashMessage($post);

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

    /**
     * @return array<string, mixed>
     */
    private function postFieldRules(): array
    {
        return array_merge([
            'title' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'simple_post' => ['nullable', 'boolean'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
            'remove_featured' => ['nullable', 'boolean'],
        ], shortVideoRules());
    }

    private function assertPostHasTextOrMedia(Request $request, ?Post $post): void
    {
        $text = trim((string) $request->input('content', ''));
        $title = trim((string) $request->input('title', ''));
        [$hasPhoto, $hasVideo] = $this->mediaPresence($request, $post);
        $hasUserTitle = $title !== '' && ! $request->boolean('simple_post');

        if ($text !== '' || $hasUserTitle || $hasPhoto || $hasVideo) {
            return;
        }

        throw ValidationException::withMessages([
            'content' => 'Please write something or add a photo or video.',
        ]);
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    private function mediaPresence(Request $request, ?Post $post): array
    {
        $hasPhoto = $request->hasFile('featured_image') || $request->hasFile('images');
        $hasVideo = $request->hasFile('videos');
        $removeIds = $this->removeMediaIds($request);

        if ($post === null) {
            return [$hasPhoto, $hasVideo];
        }

        $post->loadMissing('images');

        if (filled($post->featured_image) && ! $request->boolean('remove_featured')) {
            $hasPhoto = true;
        }

        foreach ($post->images as $image) {
            if (in_array($image->id, $removeIds, true)) {
                continue;
            }

            if ($image->isVideo()) {
                $hasVideo = true;
            } else {
                $hasPhoto = true;
            }
        }

        return [$hasPhoto, $hasVideo];
    }

    /**
     * @return array{title: string, generated: bool}
     */
    private function resolvedTitle(Request $request, ?Post $post, bool $hasPhoto, bool $hasVideo): array
    {
        $provided = trim((string) $request->input('title', ''));

        if (! $request->boolean('simple_post') && $provided !== '') {
            return [
                'title' => mb_substr($provided, 0, 255),
                'generated' => false,
            ];
        }

        $author = trim((string) ($request->user()?->name ?? $post?->user?->name ?? ''));

        if ($author === '') {
            $author = 'someone';
        }

        return [
            'title' => $this->titleFromBody(
                trim((string) $request->input('content', '')),
                $hasPhoto,
                $hasVideo,
                $author,
            ),
            'generated' => true,
        ];
    }

    private function titleFromBody(string $text, bool $hasPhoto, bool $hasVideo, string $authorName): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text !== '') {
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $chosen = [];

            foreach (array_slice($words, 0, 8) as $word) {
                $candidate = trim(implode(' ', [...$chosen, $word]));

                if (mb_strlen($candidate) > 80) {
                    break;
                }

                $chosen[] = $word;
            }

            $title = trim(implode(' ', $chosen));

            if ($title === '') {
                $title = trim(mb_substr($text, 0, 80));
            }

            return mb_substr($title, 0, 255);
        }

        if ($hasPhoto) {
            return mb_substr('Photo by '.$authorName, 0, 255);
        }

        if ($hasVideo) {
            return mb_substr('Video by '.$authorName, 0, 255);
        }

        return mb_substr('Post by '.$authorName, 0, 255);
    }

    private function uniquePostSlug(string $title, ?int $ignoreId = null): string
    {
        // Emoji, Urdu, and symbols can slug to nothing, or to a lossy
        // transliteration that changes between servers. Use a stable fallback.
        $slugTitle = preg_match('/[A-Za-z0-9]/', $title) === 1 ? $title : 'post';
        $slug = generateUniqueSlug(Post::class, $slugTitle, $ignoreId);

        if ($slug !== '') {
            return $slug;
        }

        return generateUniqueSlug(Post::class, 'post', $ignoreId);
    }

    private function resolvedCategoryId(Request $request): ?int
    {
        if ($request->filled('category_id')) {
            return $request->integer('category_id');
        }

        $community = Category::query()
            ->where('status', true)
            ->where('slug', 'community')
            ->first();

        if ($community === null) {
            $community = Category::query()
                ->where('status', true)
                ->where('name', 'Community')
                ->first();
        }

        return $community?->id;
    }

    private function resolvedContent(Request $request): string
    {
        return trim((string) $request->input('content', ''));
    }

    private function resolvedExcerpt(Request $request, ?Post $post): ?string
    {
        if ($post !== null && ! $request->exists('excerpt')) {
            return $post->excerpt;
        }

        $excerpt = trim((string) $request->input('excerpt', ''));

        return $excerpt === '' ? null : $excerpt;
    }

    /**
     * @return list<int>
     */
    private function removeMediaIds(Request $request): array
    {
        return collect($request->input('remove_media', []))
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function deleteRequestedMedia(Request $request, Post $post): void
    {
        $ids = $this->removeMediaIds($request);

        if ($ids !== []) {
            $images = $post->images()->whereIn('id', $ids)->get();

            foreach ($images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }
        }

        $post->refresh();

        if ($request->boolean('remove_featured') && filled($post->featured_image)) {
            Storage::disk('public')->delete($post->featured_image);
            $post->update(['featured_image' => null]);
        }
    }

    private function attachUploadedMedia(Request $request, Post $post): void
    {
        $images = $this->uploadedFiles($request, 'images');
        $videos = $this->uploadedFiles($request, 'videos');

        if ($request->hasFile('featured_image')) {
            $previous = $post->featured_image;
            $stored = $request->file('featured_image')->store('posts/featured', 'public');
            $post->update(['featured_image' => $stored]);

            if (filled($previous) && $previous !== $stored) {
                Storage::disk('public')->delete($previous);
            }
        } elseif ($images !== [] && blank($post->featured_image)) {
            $first = array_shift($images);
            $post->update([
                'featured_image' => $first->store('posts/featured', 'public'),
            ]);
        }

        $sortOrder = (int) $post->images()->count();

        foreach ($images as $image) {
            PostImage::create([
                'post_id' => $post->id,
                'image' => $image->store('posts/images', 'public'),
                'caption' => null,
                'sort_order' => $sortOrder,
                'media_type' => 'image',
            ]);

            $sortOrder++;
        }

        foreach ($videos as $video) {
            PostImage::create([
                'post_id' => $post->id,
                'image' => $video->store('posts/videos', 'public'),
                'caption' => null,
                'sort_order' => $sortOrder,
                'media_type' => 'video',
            ]);

            $sortOrder++;
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request, string $key): array
    {
        if (! $request->hasFile($key)) {
            return [];
        }

        $files = $request->file($key);

        if ($files === null) {
            return [];
        }

        return array_values(is_array($files) ? $files : [$files]);
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
