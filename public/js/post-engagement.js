(function ($) {
    'use strict';

    if (!window.jQuery) {
        return;
    }

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    var $modal = $('#commentModal');
    var commentModal = null;
    var currentBar = null;
    var replyParentId = null;
    var inflightLikes = {};

    function showCommentModal() {
        if (! $modal.length) {
            return;
        }

        $modal.removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
        $('body').addClass('overflow-y-hidden');
    }

    function hideCommentModal() {
        if (! $modal.length) {
            return;
        }

        $modal.addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
        $('body').removeClass('overflow-y-hidden');
        currentBar = null;
        resetReplyState();
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function initials(name) {
        var parts = String(name || 'U').trim().split(/\s+/);

        return ((parts[0] || 'U').charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
    }

    function firstError(xhr) {
        var errors = xhr.responseJSON && xhr.responseJSON.errors;

        if (errors) {
            return errors[Object.keys(errors)[0]][0];
        }

        return (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.';
    }

    function commentEndpoint(id) {
        var template = $modal.data('comment-update-template') || '/comments/__ID__';

        return String(template).replace('__ID__', id);
    }

    function isLiked($bar) {
        return String($bar.data('liked')) === '1' || String($bar.attr('data-liked')) === '1';
    }

    function paintLike($bar, liked, likesCount) {
        $bar.attr('data-liked', liked ? '1' : '0');
        $bar.data('liked', liked ? 1 : 0);

        var $button = $bar.find('.js-like-button');

        $button
            .toggleClass('btn-danger', liked)
            .toggleClass('btn-outline-danger', !liked);

        $bar.find('.js-like-icon').text(liked ? '♥' : '♡');
        $bar.find('.js-like-label').text(liked ? 'Liked' : 'Like');

        if (typeof likesCount !== 'undefined' && likesCount !== null) {
            $bar.find('.js-likes-count').text(likesCount);
        }
    }

    function paintModalLike(liked, likesCount, commentsCount) {
        $('#modal-like-button')
            .toggleClass('btn-danger', liked)
            .toggleClass('btn-outline-danger', !liked);
        $('#modal-like-icon').text(liked ? '♥' : '♡');
        $('#modal-like-label').text(liked ? 'Liked' : 'Like');

        if (typeof likesCount !== 'undefined' && likesCount !== null) {
            $('#modal-likes-count').text(likesCount);
        }

        if (typeof commentsCount !== 'undefined' && commentsCount !== null) {
            $('#modal-comments-count').text(commentsCount);
            if (currentBar) {
                currentBar.find('.js-comments-count').text(commentsCount);
            }
        }
    }

    function showToast(message, type) {
        var $toast = $('#engagement-toast');

        if (!$toast.length) {
            return;
        }

        $toast
            .removeClass('d-none bg-forest-800 bg-red-600')
            .addClass(type === 'danger' ? 'bg-red-600' : 'bg-forest-800')
            .text(message)
            .show();
    }

    function showModalAlert(message, type) {
        $('#modal-engagement-alert')
            .removeClass('d-none alert-success alert-danger alert-info')
            .addClass('alert-' + (type || 'info'))
            .text(message);
    }

    function toggleLike($bar) {
        var postId = $bar.attr('data-item-key') || $bar.data('post-id');

        var likeUrl = $bar.attr('data-like-url');
        var unlikeUrl = $bar.attr('data-unlike-url');

        if (!likeUrl || inflightLikes[postId]) {
            return;
        }

        var liked = isLiked($bar);

        inflightLikes[postId] = true;

        $.ajax({
            url: liked ? unlikeUrl : likeUrl,
            method: liked ? 'DELETE' : 'POST'
        })
            .done(function (response) {
                paintLike($bar, Boolean(response.liked), response.likes_count);

                if (currentBar && (currentBar.attr('data-item-key') || currentBar.data('post-id')) === postId) {
                    paintModalLike(Boolean(response.liked), response.likes_count, null);
                }

                showToast(response.message, 'success');
            })
            .fail(function (xhr) {
                showToast(firstError(xhr), 'danger');
            })
            .always(function () {
                inflightLikes[postId] = false;
            });
    }

    function renderComment(comment, isReply) {
        var name = (comment.user && comment.user.name) || 'Unknown User';
        var actions = '';

        if (!isReply) {
            actions += '<button type="button" class="btn btn-link btn-sm p-0 js-reply-comment">Reply</button>';
        }

        if (comment.can_edit) {
            actions += '<button type="button" class="btn btn-link btn-sm p-0 js-edit-comment">Edit</button>';
        }

        if (comment.can_delete) {
            actions += '<button type="button" class="btn btn-link btn-sm p-0 text-danger js-delete-comment">Delete</button>';
        }

        var replies = '';

        if (!isReply) {
            replies = '<div class="comment-replies"></div>';
        }

        var $item = $(
            '<div class="comment-item' + (isReply ? ' comment-reply' : '') + '" data-comment-id="' + comment.id + '" data-author="' + escapeHtml(name) + '">' +
                '<div class="comment-thread">' +
                    '<div class="comment-avatar">' + escapeHtml(initials(name)) + '</div>' +
                    '<div class="flex-grow-1">' +
                        '<div class="comment-bubble">' +
                            '<strong>' + escapeHtml(name) + '</strong>' +
                            '<p class="comment-content">' + escapeHtml(comment.content) + '</p>' +
                        '</div>' +
                        '<div class="comment-edit-form d-none mt-2">' +
                            '<textarea class="form-control form-control-sm comment-edit-input mb-2" rows="2" maxlength="2000"></textarea>' +
                            '<button type="button" class="btn btn-sm btn-primary js-save-edit">Save</button> ' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary js-cancel-edit">Cancel</button>' +
                            '<div class="text-danger small comment-edit-error"></div>' +
                        '</div>' +
                        '<div class="comment-meta-actions d-flex gap-3 text-muted">' +
                            '<span>' + escapeHtml(comment.created_at || '') + '</span>' +
                            actions +
                        '</div>' +
                        replies +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        if (comment.replies && comment.replies.length) {
            comment.replies.forEach(function (reply) {
                $item.find('.comment-replies').append(renderComment(reply, true));
            });
        }

        return $item;
    }

    function renderComments(comments) {
        var $list = $('#modal-comments-list').empty();

        (comments || []).forEach(function (comment) {
            $list.append(renderComment(comment, false));
        });

        $('#modal-comments-empty').toggleClass('d-none', (comments || []).length > 0);
    }

    function resetReplyState() {
        replyParentId = null;
        $('#replying-to').addClass('d-none');
        $('#modal-comment-input').attr('placeholder', 'Write a comment...');
    }

    function openComments($bar) {
        currentBar = $bar;
        resetReplyState();
        $('#modal-comment-error').text('');
        $('#modal-engagement-alert').addClass('d-none').text('');
        $('#commentModalSubtitle').text($bar.attr('data-post-title') || '');
        $('#modal-comments-loading').removeClass('d-none');
        $('#modal-comments-empty').addClass('d-none');
        $('#modal-comments-list').empty();
        paintModalLike(isLiked($bar), $bar.find('.js-likes-count').text(), $bar.find('.js-comments-count').text());

        if (commentModal) {
            commentModal.show();
        } else {
            showCommentModal();
        }

        $.getJSON($bar.attr('data-comments-url'))
            .done(function (response) {
                paintLike($bar, Boolean(response.liked), response.likes_count);
                paintModalLike(Boolean(response.liked), response.likes_count, response.comments_count);
                renderComments(response.comments);
            })
            .fail(function (xhr) {
                showModalAlert(firstError(xhr), 'danger');
            })
            .always(function () {
                $('#modal-comments-loading').addClass('d-none');
            });
    }

    $(document).on('click', '.js-like-button', function (event) {
        event.preventDefault();
        toggleLike($(this).closest('.post-engagement-bar'));
    });

    $(document).on('click', '.js-comment-button', function (event) {
        event.preventDefault();
        openComments($(this).closest('.post-engagement-bar'));
    });

    $('#modal-like-button').on('click', function () {
        if (currentBar) {
            toggleLike(currentBar);
        }
    });

    $('#modal-comment-submit').on('click', function () {
        if (!currentBar) {
            return;
        }

        var $button = $(this);
        var $error = $('#modal-comment-error');
        var content = $.trim($('#modal-comment-input').val());

        $error.text('');

        if (content.length < 3) {
            $error.text('Please enter at least 3 characters.');
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: currentBar.attr('data-comment-url'),
            method: 'POST',
            data: {
                content: content,
                parent_id: replyParentId
            }
        })
            .done(function (response) {
                $('#modal-comment-input').val('');
                $('#modal-comments-empty').addClass('d-none');

                if (response.comment.parent_id) {
                    var $parent = $('#modal-comments-list').find('.comment-item[data-comment-id="' + response.comment.parent_id + '"]');
                    $parent.find('> .comment-thread .comment-replies').append(renderComment(response.comment, true));
                } else {
                    $('#modal-comments-list').prepend(renderComment(response.comment, false));
                }

                paintModalLike(isLiked(currentBar), null, response.comments_count);
                showModalAlert(response.message, 'success');
                resetReplyState();
            })
            .fail(function (xhr) {
                $error.text(firstError(xhr));
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.js-reply-comment', function () {
        var $item = $(this).closest('.comment-item');

        replyParentId = $item.data('comment-id');
        $('#replying-to').removeClass('d-none');
        $('#replying-to-name').text($item.data('author'));
        $('#modal-comment-input').attr('placeholder', 'Write a reply...').trigger('focus');
    });

    $('#cancel-reply').on('click', function () {
        resetReplyState();
    });

    $(document).on('click', '.js-edit-comment', function () {
        var $item = $(this).closest('.comment-item');
        var content = $.trim($item.find('.comment-content').first().text());

        $item.find('.comment-bubble, .comment-meta-actions').first().addClass('d-none');
        $item.find('.comment-edit-form').removeClass('d-none');
        $item.find('.comment-edit-input').val(content).trigger('focus');
    });

    $(document).on('click', '.js-cancel-edit', function () {
        var $item = $(this).closest('.comment-item');

        $item.find('.comment-edit-form').addClass('d-none');
        $item.find('.comment-bubble, .comment-meta-actions').removeClass('d-none');
    });

    $(document).on('click', '.js-save-edit', function () {
        var $item = $(this).closest('.comment-item');
        var $error = $item.find('.comment-edit-error');
        var $button = $(this);
        var content = $.trim($item.find('.comment-edit-input').val());

        $error.text('');

        if (content.length < 3) {
            $error.text('Please enter at least 3 characters.');
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: commentEndpoint($item.data('comment-id')),
            method: 'PUT',
            data: { content: content }
        })
            .done(function (response) {
                $item.find('.comment-content').first().text(response.comment.content);
                $item.find('.comment-edit-form').addClass('d-none');
                $item.find('.comment-bubble, .comment-meta-actions').removeClass('d-none');
                showModalAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                $error.text(firstError(xhr));
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.js-delete-comment', function () {
        if (!window.confirm('Delete this comment?')) {
            return;
        }

        var $item = $(this).closest('.comment-item');
        var $button = $(this);

        $button.prop('disabled', true);

        $.ajax({
            url: commentEndpoint($item.data('comment-id')),
            method: 'DELETE'
        })
            .done(function (response) {
                $item.remove();
                paintModalLike(currentBar ? isLiked(currentBar) : false, null, response.comments_count);
                $('#modal-comments-empty').toggleClass('d-none', $('#modal-comments-list .comment-item').length > 0);
                showModalAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                showModalAlert(firstError(xhr), 'danger');
                $button.prop('disabled', false);
            });
    });

    $modal.on('click', function (event) {
        if (event.target === $modal[0]) {
            hideCommentModal();
        }
    });

    $modal.on('click', '.js-comment-modal-close', function () {
        hideCommentModal();
    });

    $modal.on('hidden.bs.modal', function () {
        hideCommentModal();
    });
})(window.jQuery);
