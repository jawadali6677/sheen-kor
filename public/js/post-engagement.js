(function ($) {
    'use strict';

    var $root = $('#post-engagement');

    if (!$root.length) {
        return;
    }

    var likeUrl = $root.data('like-url');
    var unlikeUrl = $root.data('unlike-url');
    var commentUrl = $root.data('comment-url');
    var commentUpdateTemplate = $root.data('comment-update-template');
    var liked = String($root.data('liked')) === '1';
    var requestInFlight = false;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function showAlert(message, type) {
        var $alert = $('#engagement-alert');

        $alert
            .removeClass('d-none alert-success alert-danger alert-info')
            .addClass('alert-' + (type || 'info'))
            .text(message);
    }

    function hideAlert() {
        $('#engagement-alert').addClass('d-none').text('');
    }

    function firstValidationError(xhr) {
        var errors = xhr.responseJSON && xhr.responseJSON.errors;

        if (!errors) {
            return (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.';
        }

        var key = Object.keys(errors)[0];

        return errors[key][0];
    }

    function commentEndpoint(id) {
        return String(commentUpdateTemplate).replace('__ID__', id);
    }

    function updateLikeButton() {
        var $button = $('#like-button');

        $button
            .toggleClass('btn-danger', liked)
            .toggleClass('btn-outline-danger', !liked);

        $('#like-icon').text(liked ? '♥' : '♡');
        $('#like-label').text(liked ? 'Liked' : 'Like');
    }

    function setCounts(likesCount, commentsCount) {
        if (typeof likesCount !== 'undefined' && likesCount !== null) {
            $('#likes-count').text(likesCount);
        }

        if (typeof commentsCount !== 'undefined' && commentsCount !== null) {
            $('#comments-count').text(commentsCount);
            $('#comments-empty').toggleClass('d-none', Number(commentsCount) > 0);
        }
    }

    function renderComment(comment) {
        var isReply = Boolean(comment.parent_id);
        var actions = '';

        if (!isReply) {
            actions += '<button type="button" class="btn btn-sm btn-outline-primary btn-reply">Reply</button>';
        }

        if (comment.can_edit) {
            actions += '<button type="button" class="btn btn-sm btn-outline-secondary btn-edit">Edit</button>';
        }

        if (comment.can_delete) {
            actions += '<button type="button" class="btn btn-sm btn-outline-danger btn-delete">Delete</button>';
        }

        var replyMarkup = '';

        if (!isReply) {
            replyMarkup =
                '<div class="comment-reply-form d-none mt-3">' +
                    '<textarea class="form-control comment-reply-input mb-2" rows="2" maxlength="2000" placeholder="Write a reply..."></textarea>' +
                    '<div class="d-flex gap-2">' +
                        '<button type="button" class="btn btn-sm btn-primary btn-submit-reply">Post reply</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-reply">Cancel</button>' +
                    '</div>' +
                    '<div class="invalid-feedback d-block comment-reply-error"></div>' +
                '</div>' +
                '<div class="replies mt-3 ms-md-4"></div>';
        }

        return $(
            '<div class="card mb-3 comment-item' + (isReply ? ' border-0 bg-light' : '') + '" data-comment-id="' + comment.id + '" data-parent-id="' + (comment.parent_id || '') + '">' +
                '<div class="card-body py-3">' +
                    '<div class="d-flex justify-content-between align-items-start gap-3">' +
                        '<div>' +
                            '<strong class="comment-author">' + escapeHtml(comment.user && comment.user.name) + '</strong>' +
                            '<small class="text-muted comment-time ms-2">' + escapeHtml(comment.created_at) + '</small>' +
                        '</div>' +
                    '</div>' +
                    '<p class="comment-content mt-2 mb-2 mb-md-3">' + escapeHtml(comment.content) + '</p>' +
                    '<div class="comment-edit-form d-none">' +
                        '<textarea class="form-control comment-edit-input mb-2" rows="3" maxlength="2000"></textarea>' +
                        '<div class="d-flex gap-2">' +
                            '<button type="button" class="btn btn-sm btn-primary btn-save-edit">Save</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-edit">Cancel</button>' +
                        '</div>' +
                        '<div class="invalid-feedback d-block comment-edit-error"></div>' +
                    '</div>' +
                    '<div class="comment-actions d-flex flex-wrap gap-2">' + actions + '</div>' +
                    replyMarkup +
                '</div>' +
            '</div>'
        );
    }

    $('#like-button').on('click', function () {
        if (requestInFlight) {
            return;
        }

        requestInFlight = true;
        hideAlert();

        $.ajax({
            url: liked ? unlikeUrl : likeUrl,
            method: liked ? 'DELETE' : 'POST'
        })
            .done(function (response) {
                liked = Boolean(response.liked);
                $root.attr('data-liked', liked ? '1' : '0');
                updateLikeButton();
                setCounts(response.likes_count, null);
                showAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                showAlert(firstValidationError(xhr), 'danger');
            })
            .always(function () {
                requestInFlight = false;
            });
    });

    $('#comment-form').on('submit', function (event) {
        event.preventDefault();

        var $error = $('#comment-error');
        var content = $.trim($('#comment-content').val());

        $error.text('');

        if (content.length < 3) {
            $error.text('Please enter at least 3 characters.');
            return;
        }

        $('#comment-submit').prop('disabled', true);
        hideAlert();

        $.ajax({
            url: commentUrl,
            method: 'POST',
            data: { content: content }
        })
            .done(function (response) {
                $('#comment-content').val('');
                $('#comments-list').prepend(renderComment(response.comment));
                setCounts(null, response.comments_count);
                showAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                $error.text(firstValidationError(xhr));
            })
            .always(function () {
                $('#comment-submit').prop('disabled', false);
            });
    });

    $root.on('click', '.btn-reply', function () {
        var $item = $(this).closest('.comment-item');

        $item.find('> .card-body > .comment-reply-form').removeClass('d-none');
        $item.find('.comment-reply-input').trigger('focus');
    });

    $root.on('click', '.btn-cancel-reply', function () {
        var $form = $(this).closest('.comment-reply-form');

        $form.addClass('d-none');
        $form.find('.comment-reply-input').val('');
        $form.find('.comment-reply-error').text('');
    });

    $root.on('click', '.btn-submit-reply', function () {
        var $item = $(this).closest('.comment-item');
        var $form = $item.find('> .card-body > .comment-reply-form');
        var $error = $form.find('.comment-reply-error');
        var $button = $(this);
        var content = $.trim($form.find('.comment-reply-input').val());
        var parentId = $item.data('comment-id');

        $error.text('');

        if (content.length < 3) {
            $error.text('Please enter at least 3 characters.');
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: commentUrl,
            method: 'POST',
            data: {
                content: content,
                parent_id: parentId
            }
        })
            .done(function (response) {
                $item.find('> .card-body > .replies').append(renderComment(response.comment));
                $form.addClass('d-none');
                $form.find('.comment-reply-input').val('');
                setCounts(null, response.comments_count);
                showAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                $error.text(firstValidationError(xhr));
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $root.on('click', '.btn-edit', function () {
        var $item = $(this).closest('.comment-item');
        var current = $item.find('> .card-body > .comment-content').text();

        $item.find('> .card-body > .comment-content, > .card-body > .comment-actions').addClass('d-none');
        $item.find('> .card-body > .comment-edit-form').removeClass('d-none');
        $item.find('.comment-edit-input').val($.trim(current)).trigger('focus');
        $item.find('.comment-edit-error').text('');
    });

    $root.on('click', '.btn-cancel-edit', function () {
        var $item = $(this).closest('.comment-item');

        $item.find('> .card-body > .comment-edit-form').addClass('d-none');
        $item.find('> .card-body > .comment-content, > .card-body > .comment-actions').removeClass('d-none');
        $item.find('.comment-edit-error').text('');
    });

    $root.on('click', '.btn-save-edit', function () {
        var $item = $(this).closest('.comment-item');
        var $error = $item.find('.comment-edit-error');
        var $button = $(this);
        var content = $.trim($item.find('.comment-edit-input').val());
        var commentId = $item.data('comment-id');

        $error.text('');

        if (content.length < 3) {
            $error.text('Please enter at least 3 characters.');
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: commentEndpoint(commentId),
            method: 'PUT',
            data: { content: content }
        })
            .done(function (response) {
                $item.find('> .card-body > .comment-content').text(response.comment.content);
                $item.find('> .card-body > .comment-edit-form').addClass('d-none');
                $item.find('> .card-body > .comment-content, > .card-body > .comment-actions').removeClass('d-none');
                showAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                $error.text(firstValidationError(xhr));
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $root.on('click', '.btn-delete', function () {
        if (!window.confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        var $item = $(this).closest('.comment-item');
        var $button = $(this);
        var commentId = $item.data('comment-id');

        $button.prop('disabled', true);
        hideAlert();

        $.ajax({
            url: commentEndpoint(commentId),
            method: 'DELETE'
        })
            .done(function (response) {
                $item.remove();
                setCounts(null, response.comments_count);
                showAlert(response.message, 'success');
            })
            .fail(function (xhr) {
                showAlert(firstValidationError(xhr), 'danger');
                $button.prop('disabled', false);
            });
    });
})(jQuery);
