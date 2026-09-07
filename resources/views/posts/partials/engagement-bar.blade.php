@php
    $model = $model ?? $post;
    $isAlert = $model instanceof \App\Models\Alert;
    $liked = $liked ?? (bool) ($model->liked_by_user ?? false);
    $likesCount = $likesCount ?? ($model->likes_count ?? 0);
    $commentsCount = $commentsCount ?? ($model->comments_count ?? 0);
    $canEngage = $isAlert || $model->status === 'published';
    $likeStore = $isAlert ? route('alerts.likes.store', $model) : route('posts.likes.store', $model);
    $likeDestroy = $isAlert ? route('alerts.likes.destroy', $model) : route('posts.likes.destroy', $model);
    $commentsIndex = $isAlert ? route('alerts.comments.index', $model) : route('posts.comments.index', $model);
    $commentsStore = $isAlert ? route('alerts.comments.store', $model) : route('posts.comments.store', $model);
    $compact = $compact ?? false;
@endphp

<div
    class="post-engagement-bar {{ $compact ? 'compact' : 'mt-4 border-t border-gray-100 pt-4' }}"
    data-item-key="{{ $isAlert ? 'alert' : 'post' }}-{{ $model->id }}"
    data-post-id="{{ $model->id }}"
    data-post-title="{{ $model->title }}"
    data-published="{{ $canEngage ? '1' : '0' }}"
    data-liked="{{ $liked ? '1' : '0' }}"
    data-like-url="{{ $likeStore }}"
    data-unlike-url="{{ $likeDestroy }}"
    data-comments-url="{{ $commentsIndex }}"
    data-comment-url="{{ $commentsStore }}"
>
    <div class="mb-2 text-sm font-medium text-gray-600">
        <span class="js-likes-count">{{ $likesCount }}</span> likes
        · <span class="js-comments-count">{{ $commentsCount }}</span> comments
    </div>

    @if($canEngage && auth()->check())
        <div class="flex gap-2">
            <button type="button" class="js-like-button inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold {{ $liked ? 'bg-red-50 text-red-700 btn-danger' : 'bg-sand-50 text-forest-800' }}">
                <span class="js-like-icon">{{ $liked ? '♥' : '♡' }}</span>
                <span class="js-like-label">{{ $liked ? 'Liked' : 'Like' }}</span>
            </button>
            <button type="button" class="js-comment-button inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-sand-50 px-3 py-2 text-sm font-semibold text-forest-800">
                Comment
            </button>
        </div>
    @elseif($canEngage)
        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-sand-50 px-3 py-2 text-sm font-semibold text-forest-800">
            Log in to like and comment
        </a>
    @else
        <p class="text-sm text-gray-500">Likes and comments are available after this story is published.</p>
    @endif
</div>
