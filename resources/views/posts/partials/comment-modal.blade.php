<div
    class="modal fade"
    id="commentModal"
    tabindex="-1"
    aria-labelledby="commentModalTitle"
    aria-hidden="true"
    data-comment-update-template="{{ url('comments/__ID__') }}"
>
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="commentModalTitle">Comments</h5>
                    <div class="small text-muted" id="commentModalSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="modal-engagement-alert" class="alert d-none py-2" role="alert"></div>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="modal-like-button">
                        <span id="modal-like-icon">♡</span>
                        <span id="modal-like-label">Like</span>
                    </button>
                    <span class="text-muted small">
                        <span id="modal-likes-count">0</span> likes ·
                        <span id="modal-comments-count">0</span> comments
                    </span>
                </div>

                <div id="modal-comments-loading" class="text-center text-muted py-4">
                    Loading comments...
                </div>

                <div id="modal-comments-empty" class="text-center text-muted py-4 d-none">
                    No comments yet. Start the conversation.
                </div>

                <div id="modal-comments-list"></div>
            </div>

            <div class="modal-footer flex-column align-items-stretch">
                <div id="replying-to" class="d-none small mb-2">
                    Replying to <strong id="replying-to-name"></strong>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-2" id="cancel-reply">Cancel</button>
                </div>
                <label for="modal-comment-input" class="form-label visually-hidden">Write a comment</label>
                <textarea
                    id="modal-comment-input"
                    class="form-control mb-2"
                    rows="2"
                    maxlength="2000"
                    placeholder="Write a comment..."
                ></textarea>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div id="modal-comment-error" class="text-danger small"></div>
                    <button type="button" class="btn btn-primary" id="modal-comment-submit">Post</button>
                </div>
            </div>
        </div>
    </div>
</div>
