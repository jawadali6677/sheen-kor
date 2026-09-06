<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        if ($user->hasPermission(Permission::ModerateComments)) {
            return true;
        }

        $commentable = $comment->commentable;

        return $commentable !== null && $commentable->user_id === $user->id;
    }
}
