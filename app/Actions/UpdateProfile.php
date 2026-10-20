<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfile
{
    /**
     * @var list<string>
     */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const IMAGE_MAX_KILOBYTES = 5120;

    /**
     * Rules the website form and the mobile app both enforce for a profile image.
     *
     * @return array{mimes: list<string>, max_kilobytes: int}
     */
    public static function imageRules(): array
    {
        return [
            'mimes' => self::IMAGE_EXTENSIONS,
            'max_kilobytes' => self::IMAGE_MAX_KILOBYTES,
        ];
    }

    /**
     * Shared profile field rules for the website form request and the mobile API.
     *
     * @return array<string, mixed>
     */
    public static function rules(User $user): array
    {
        $imageRules = ['nullable', 'image', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'max:'.self::IMAGE_MAX_KILOBYTES];

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9._]+$/',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_image' => $imageRules,
            'cover_image' => $imageRules,
            'remove_profile_image' => ['sometimes', 'boolean'],
            'remove_cover_image' => ['sometimes', 'boolean'],
        ];
    }

    public static function prepare(Request $request): void
    {
        $username = $request->input('username');
        $website = $request->input('website');

        $request->merge([
            'username' => filled($username) ? Str::lower(trim((string) $username)) : null,
            'website' => filled($website) ? trim((string) $website) : null,
            'bio' => filled($request->input('bio')) ? trim((string) $request->input('bio')) : null,
            'location' => filled($request->input('location')) ? trim((string) $request->input('location')) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, Request $request): User
    {
        $profileData = collect($attributes)->except([
            'profile_image',
            'cover_image',
            'remove_profile_image',
            'remove_cover_image',
        ])->all();

        if (! filled($profileData['username'] ?? null)) {
            unset($profileData['username']);
        }

        $user->fill($profileData);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->boolean('remove_profile_image') && $user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->profile_image = null;
        }

        if ($request->boolean('remove_cover_image') && $user->cover_image) {
            Storage::disk('public')->delete($user->cover_image);
            $user->cover_image = null;
        }

        if ($request->hasFile('profile_image')) {
            $oldImage = $user->profile_image;
            $user->profile_image = $request->file('profile_image')->store('profiles/avatars', 'public');

            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        if ($request->hasFile('cover_image')) {
            $oldCover = $user->cover_image;
            $user->cover_image = $request->file('cover_image')->store('profiles/covers', 'public');

            if ($oldCover) {
                Storage::disk('public')->delete($oldCover);
            }
        }

        $user->save();

        return $user;
    }
}
