@php
    $liked = $liked ?? (bool) ($post->liked_by_user ?? false);
    $likesCount = $likesCount ?? ($post->likes_count ?? 0);
    $commentsCount = $commentsCount ?? ($post->comments_count ?? 0);
    $isPublished = $post->status === 'published';
@endphp

<div
    class="post-engagement-bar border-t mt-4 pt-4"
    data-post-id="{{ $post->id }}"
    data-post-title="{{ $post->title }}"
    data-published="{{ $isPublished ? '1' : '0' }}"
    data-liked="{{ $liked ? '1' : '0' }}"
    data-like-url="{{ route('posts.likes.store', $post) }}"
    data-unlike-url="{{ route('posts.likes.destroy', $post) }}"
    data-comments-url="{{ route('posts.comments.index', $post) }}"
    data-comment-url="{{ route('posts.comments.store', $post) }}"
>
    <div class="d-flex align-items-center justify-content-between text-muted small mb-2">
        <span>
            <span class="js-likes-count">{{ $likesCount }}</span> likes
        </span>
        <span>
            <span class="js-comments-count">{{ $commentsCount }}</span> comments
        </span>
    </div>

    @if($isPublished)
        <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
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
