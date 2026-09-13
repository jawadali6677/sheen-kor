<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->where(function ($query): void {
                $query->whereNull('username')
                    ->orWhere('username', '');
            })
            ->orderBy('id')
            ->each(function (User $user): void {
                $user->forceFill([
                    'username' => generateUniqueUsername((string) ($user->name ?: 'user'), $user->id),
                ])->save();
            });
    }

    public function down(): void
    {
        // Usernames remain assigned; they are public identifiers.
    }
};
