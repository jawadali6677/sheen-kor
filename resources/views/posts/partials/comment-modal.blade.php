<div
    id="commentModal"
    class="hidden fixed inset-0 z-50 items-center justify-center bg-forest-950/50 px-4 py-8"
    tabindex="-1"
    aria-labelledby="commentModalTitle"
    aria-hidden="true"
    data-comment-update-template="{{ url('comments/__ID__') }}"
>
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-card" role="dialog">
        <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-forest-900" id="commentModalTitle">Comments</h2>
                <p class="text-sm text-gray-500" id="commentModalSubtitle"></p>
            </div>
            <button type="button" class="js-comment-modal-close rounded-full p-2 text-gray-500 hover:bg-sand-50" aria-label="Close">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div id="modal-engagement-alert" class="alert d-none mb-3 rounded-xl px-3 py-2 text-sm" role="alert"></div>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button type="button" class="btn-secondary" id="modal-like-button">
                    <span id="modal-like-icon">♡</span>
                    <span id="modal-like-label">Like</span>
                </button>
                <span class="text-sm text-gray-500">
                    <span id="modal-likes-count">0</span> likes ·
                    <span id="modal-comments-count">0</span> comments
                </span>
            </div>
            <div id="modal-comments-loading" class="py-8 text-center text-sm text-gray-500">Loading comments...</div>
            <div id="modal-comments-empty" class="d-none py-8 text-center text-sm text-gray-500">No comments yet. Start the conversation.</div>
            <div id="modal-comments-list"></div>
        </div>

        <div class="border-t border-gray-100 px-5 py-4">
            <div id="replying-to" class="d-none mb-2 text-sm text-gray-500">
                Replying to <strong id="replying-to-name"></strong>
                <button type="button" class="btn-link ms-2" id="cancel-reply">Cancel</button>
            </div>
            <label for="modal-comment-input" class="sr-only">Write a comment</label>
            <textarea
                id="modal-comment-input"
                class="sk-input mb-2"
                rows="2"
                maxlength="2000"
                placeholder="Write a comment..."
            ></textarea>
            <div class="flex items-center justify-between gap-2">
                <div id="modal-comment-error" class="text-sm text-red-600"></div>
                <button type="button" class="btn-primary" id="modal-comment-submit">Post</button>
            </div>
        </div>
    </div>
</div>
