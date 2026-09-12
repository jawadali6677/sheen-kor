<?php

namespace App\Http\Controllers;

use App\Actions\AwardScore;
use App\Actions\ModerateContent;
use App\Actions\RevokeScore;
use App\Enums\ModerationDecision;
use App\Enums\Permission;
use App\Enums\ScoreReason;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Notifications\PostNeedsReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PostController extends Controller
{
    public function __construct(
        private AwardScore $awardScore,
        private RevokeScore $revokeScore,
        private ModerateContent $moderateContent,
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
    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $request->validate(array_merge([
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'excerpt' => [
                'nullable',
                'string',
                'max:500',
            ],

            'content' => [
                'required',
                'string',
                'min:20',
            ],

            'featured_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ], shortVideoRules()));

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Create Post
            |--------------------------------------------------------------------------
            */

            $post = Post::create([
                'user_id' => auth()->id(),
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => generateUniqueSlug(
                    Post::class,
                    $request->title
                ),
                'excerpt' => $request->excerpt,
                'content' => $request->content,
                'status' => 'pending',
                'published_at' => null,
                'views' => 0,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Featured Image
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('featured_image')) {

                $featuredImage = $request
                    ->file('featured_image')
                    ->store('posts/featured', 'public');

                $post->update([
                    'featured_image' => $featuredImage,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Additional Images
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images') || $request->hasFile('videos')) {
                $sortOrder = 0;

                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        PostImage::create([
                            'post_id' => $post->id,
                            'image' => $image->store('posts/images', 'public'),
                            'caption' => null,
                            'sort_order' => $sortOrder,
                            'media_type' => 'image',
                        ]);

                        $sortOrder++;
                    }
                }

                if ($request->hasFile('videos')) {
                    foreach ($request->file('videos') as $video) {
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
            }

            $this->awardScore->handle($request->user(), ScoreReason::PostCreated, $post);

            DB::commit();
        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Something went wrong while creating your story.'
                );
        }

        $this->applyContentModeration($post);

        return redirect()
            ->route('posts.index')
            ->with(
                'success',
                $this->moderationFlashMessage($post, created: true)
            );
    }

    /**
     * Display a single post.
     */
    public function show(Post $post)
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
            'user',
            'category',
            'images',
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
                'user',
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
    public function update(Request $request, Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        $this->authorize('update', $post);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $request->validate(array_merge([
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'excerpt' => [
                'nullable',
                'string',
                'max:500',
            ],

            'content' => [
                'required',
                'string',
                'min:20',
            ],

            'featured_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ], shortVideoRules()));

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Update Post
            |--------------------------------------------------------------------------
            */

            $post->update([
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => generateUniqueSlug(
                    Post::class,
                    $request->title,
                    $post->id
                ),
                'excerpt' => $request->excerpt,
                'content' => $request->content,

                /*
                | Send edited post back to moderation.
                */
                'status' => 'pending',
                'published_at' => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Featured Image
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('featured_image')) {

                $oldFeaturedImage = $post->featured_image;

                $newFeaturedImage = $request
                    ->file('featured_image')
                    ->store('posts/featured', 'public');

                $post->update([
                    'featured_image' => $newFeaturedImage,
                ]);

                if ($oldFeaturedImage) {

                    Storage::disk('public')
                        ->delete($oldFeaturedImage);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Add New Additional Images
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images') || $request->hasFile('videos')) {
                $sortOrder = $post->images()->count();

                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        PostImage::create([
                            'post_id' => $post->id,
                            'image' => $image->store('posts/images', 'public'),
                            'caption' => null,
                            'sort_order' => $sortOrder,
                            'media_type' => 'image',
                        ]);

                        $sortOrder++;
                    }
                }

                if ($request->hasFile('videos')) {
                    foreach ($request->file('videos') as $video) {
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
            }

            DB::commit();
        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Something went wrong while updating your story.'
                );
        }

        $this->applyContentModeration($post);

        return redirect()
            ->route('posts.index')
            ->with(
                'success',
                $this->moderationFlashMessage($post, created: false)
            );
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
                    'Your story has been deleted successfully.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->with(
                    'error',
                    'Something went wrong while deleting your story.'
                );
        }
    }

    private function applyContentModeration(Post $post): void
    {
        try {
            $post->refresh()->load('images');

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

            $decision = $this->moderateContent->handle($text, $imagePaths, $videoPaths);

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

            $this->notifyReviewersIfPending($post);
        } catch (Throwable $exception) {
            report($exception);

            $post->update([
                'status' => 'pending',
                'published_at' => null,
            ]);

            $this->notifyReviewersIfPending($post);
        }
    }

    private function notifyReviewersIfPending(Post $post): void
    {
        $post->refresh()->loadMissing('user');

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

    private function moderationFlashMessage(Post $post, bool $created): string
    {
        $post->refresh();

        return match ($post->status) {
            'published' => $created
                ? 'Your story has been published.'
                : 'Your story has been updated and published.',
            'rejected' => 'Your story was not published because it did not meet community guidelines.',
            default => $created
                ? 'Your story has been submitted successfully and is awaiting review.'
                : 'Your story has been updated successfully and is awaiting review.',
        };
    }
}
