@php
    $isReply = (bool) $comment->parent_id;
    $canEdit = auth()->id() === $comment->user_id;
    $canDelete = $canEdit || auth()->id() === $post->user_id;
@endphp

<div
    class="card mb-3 comment-item {{ $isReply ? 'border-0 bg-light' : '' }}"
    data-comment-id="{{ $comment->id }}"
    data-parent-id="{{ $comment->parent_id }}"
>
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <strong class="comment-author">{{ $comment->user?->name ?? 'Unknown User' }}</strong>
                <small class="text-muted comment-time ms-2">{{ $comment->created_at?->diffForHumans() }}</small>
            </div>
        </div>

        <p class="comment-content mt-2 mb-2 mb-md-3">{{ $comment->content }}</p>

        <div class="comment-edit-form d-none">
            <textarea class="form-control comment-edit-input mb-2" rows="3" maxlength="2000">{{ $comment->content }}</textarea>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary btn-save-edit">Save</button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-edit">Cancel</button>
            </div>
            <div class="invalid-feedback d-block comment-edit-error"></div>
        </div>

        <div class="comment-actions d-flex flex-wrap gap-2">
            @if(! $isReply && $post->status === 'published')
                <button type="button" class="btn btn-sm btn-outline-primary btn-reply">Reply</button>
            @endif

            @if($canEdit)
                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit">Edit</button>
            @endif

            @if($canDelete)
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete">Delete</button>
            @endif
        </div>

        @if(! $isReply)
            <div class="comment-reply-form d-none mt-3">
                <textarea class="form-control comment-reply-input mb-2" rows="2" maxlength="2000" placeholder="Write a reply..."></textarea>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary btn-submit-reply">Post reply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-reply">Cancel</button>
                </div>
                <div class="invalid-feedback d-block comment-reply-error"></div>
            </div>

            <div class="replies mt-3 ms-md-4">
                @foreach($comment->replies as $reply)
                    @include('posts.partials.comment', ['comment' => $reply, 'post' => $post])
                @endforeach
            </div>
        @endif
    </div>
</div>
