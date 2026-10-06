<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    public function handle(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => Role::USER,
            'status' => true,
        ]);

        event(new Registered($user));

        return $user;
    }
}
