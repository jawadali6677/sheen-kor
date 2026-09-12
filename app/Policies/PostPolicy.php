<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreatePosts);
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id
            || $user->hasPermission(Permission::ModeratePosts);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function moderate(User $user): bool
    {
        return $user->hasPermission(Permission::ModeratePosts);
    }
}
