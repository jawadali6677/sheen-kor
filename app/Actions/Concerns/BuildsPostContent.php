<?php

namespace App\Actions\Concerns;

use App\Jobs\ModeratePostContent;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait BuildsPostContent
{
    /**
     * @return array<string, mixed>
     */
    protected function postFieldRules(): array
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

    protected function assertPostHasTextOrMedia(Request $request, ?Post $post): void
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
    protected function mediaPresence(Request $request, ?Post $post): array
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
    protected function resolvedTitle(Request $request, ?Post $post, bool $hasPhoto, bool $hasVideo): array
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

    protected function titleFromBody(string $text, bool $hasPhoto, bool $hasVideo, string $authorName): string
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

    protected function uniquePostSlug(string $title, ?int $ignoreId = null): string
    {
        $slugTitle = preg_match('/[A-Za-z0-9]/', $title) === 1 ? $title : 'post';
        $slug = generateUniqueSlug(Post::class, $slugTitle, $ignoreId);

        if ($slug !== '') {
            return $slug;
        }

        return generateUniqueSlug(Post::class, 'post', $ignoreId);
    }

    protected function resolvedCategoryId(Request $request): ?int
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

    protected function resolvedContent(Request $request): string
    {
        return trim((string) $request->input('content', ''));
    }

    protected function resolvedExcerpt(Request $request, ?Post $post): ?string
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
    protected function removeMediaIds(Request $request): array
    {
        return collect($request->input('remove_media', []))
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function deleteRequestedMedia(Request $request, Post $post): void
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

    protected function attachUploadedMedia(Request $request, Post $post): void
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
    protected function uploadedFiles(Request $request, string $key): array
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

    protected function queueContentModeration(Post $post): void
    {
        $post->refresh()->load('images');

        ModeratePostContent::dispatch(
            $post->id,
            ModeratePostContent::contentVersion($post),
        )->afterCommit();
    }
}
