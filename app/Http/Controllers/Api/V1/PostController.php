<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordQualifiedPostView;
use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PostController extends Controller
{
    use SerializesApiContent;

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (Category $category): array => $this->categoryPayload($category))->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $posts = $this->feed($request);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (Post $post): array => $this->postPayload($post))->values(),
            'meta' => $this->paginationMeta($posts),
        ]);
    }

    public function show(Request $request, Post $post, RecordQualifiedPostView $recordQualifiedPostView): JsonResponse
    {
        abort_unless($post->isVisibleTo($request->user()), 404);

        $viewerId = $request->user()?->id;

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
        ])
            ->loadCount([
                'likes',
                'comments' => function ($query): void {
                    $query->where('status', 'approved');
                },
            ])
            ->loadExists([
                'likes as liked_by_user' => function ($query) use ($viewerId): void {
                    $query->where('user_id', $viewerId);
                },
                'boosts as is_boosted' => function ($query): void {
                    $query->currentlyActive();
                },
            ]);

        $post->increment('views');

        $recordQualifiedPostView->handle($post, $request);

        return response()->json([
            'data' => $this->postPayload($post),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    private function feed(Request $request): LengthAwarePaginator
    {
        $category = null;

        if ($request->filled('category')) {
            $category = Category::query()
                ->where('status', true)
                ->where('slug', $request->string('category')->toString())
                ->first();
        }

        $search = trim($request->string('q')->toString());
        $viewerId = $request->user()?->id;

        return Post::query()
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
                'comments' => function ($query): void {
                    $query->where('status', 'approved');
                },
            ])
            ->withExists([
                'likes as liked_by_user' => function ($query) use ($viewerId): void {
                    $query->where('user_id', $viewerId);
                },
                'boosts as is_boosted' => function ($query): void {
                    $query->currentlyActive();
                },
            ])
            ->when($category, function ($query) use ($category): void {
                $query->where('category_id', $category->id)
                    ->where('status', 'published');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->where('status', 'published')
            ->latest('created_at')
            ->paginate(10);
    }

    /**
     * @return array{id: int, name: string, slug: string, description: string|null, image_url: string|null}
     */
    private function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $this->publicStorageUrl($category->image),
        ];
    }
}
