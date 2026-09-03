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
    class="post-engagement-bar {{ $compact ? 'compact' : 'border-t mt-4 pt-4' }}"
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
    <div class="{{ $compact ? 'feed-counts' : 'd-flex align-items-center justify-content-between text-muted small mb-2' }}">
        <span>
            <span class="js-likes-count">{{ $likesCount }}</span> likes
            @if($compact)
                · <span class="js-comments-count">{{ $commentsCount }}</span> comments
            @endif
        </span>
        @unless($compact)
        <span>
            <span class="js-comments-count">{{ $commentsCount }}</span> comments
        </span>
        @endunless
    </div>

    @if($canEngage)
        <div class="{{ $compact ? 'feed-actions-row' : 'd-grid gap-2' }}" @unless($compact) style="grid-template-columns: 1fr 1fr;" @endunless>
            <button
                type="button"
                class="btn {{ $liked ? 'btn-danger' : 'btn-outline-danger' }} js-like-button"
            >
                <span class="js-like-icon">{{ $liked ? '♥' : '♡' }}</span>
                <span class="js-like-label">{{ $liked ? 'Liked' : 'Like' }}</span>
            </button>

            <button
                type="button"
                class="btn btn-outline-primary js-comment-button"
            >
                💬 Comment
            </button>
        </div>
    @else
        <p class="small text-muted mb-0">Likes and comments are available after this story is published.</p>
    @endif
</div>
