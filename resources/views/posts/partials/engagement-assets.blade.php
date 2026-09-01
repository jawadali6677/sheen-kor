@push('styles')
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        .comment-thread {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .comment-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #0d6efd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .comment-bubble {
            background: #f1f3f5;
            border-radius: 18px;
            padding: 0.55rem 0.85rem;
            flex: 1;
        }
        .comment-bubble .comment-content {
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0.15rem 0 0;
        }
        .comment-meta-actions {
            font-size: 0.8rem;
            padding-left: 0.35rem;
        }
        .comment-replies {
            margin-top: 0.75rem;
            margin-left: 2.75rem;
            padding-left: 0.75rem;
            border-left: 2px solid #dee2e6;
        }
        .comment-reply .comment-avatar {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    </style>
@endpush

@include('posts.partials.comment-modal')

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/post-engagement.js') }}"></script>
@endpush
