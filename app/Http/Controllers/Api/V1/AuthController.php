<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteUserAccount;
use App\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function register(Request $request, RegisterUser $registerUser): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = $registerUser->handle(
            $validated['name'],
            $validated['email'],
            $validated['password'],
        );

        $token = $user->createToken($validated['device_name'], ['mobile']);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->accountUser($user),
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! $user->status) {
            throw ValidationException::withMessages([
                'email' => 'This account has been disabled.',
            ]);
        }

        $token = $user->createToken($validated['device_name'], ['mobile']);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->accountUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out of all devices.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->accountUser($request->user()),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', PasswordRule::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $current = $user->currentAccessToken();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $user->tokens()
            ->when(
                $current instanceof PersonalAccessToken,
                fn ($query) => $query->whereKeyNot($current->id),
            )
            ->delete();

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function destroy(Request $request, DeleteUserAccount $deleteUserAccount): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $deleteUserAccount->handle($request->user());

        return response()->json([
            'message' => 'Account deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role,
            'score' => (int) $user->score,
            'has_green_tick' => $user->hasActiveGreenTick(),
            'avatar_url' => $user->avatarUrl(),
            'cover_url' => $user->coverUrl(),
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => $user->website,
            'unread_notifications_count' => $user->unreadNotifications()->count(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
