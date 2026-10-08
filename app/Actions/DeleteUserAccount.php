<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAccount
{
    /**
     * Sanctum stores tokens in a morph table, so they are deleted explicitly
     * before the user row. The website session logout stays in the web controller.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
