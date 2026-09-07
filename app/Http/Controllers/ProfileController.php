<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        abort_unless($user->status || auth()->id() === $user->id, 404);

        $user->load('assignedRole');

        $user->loadCount([
            'posts as stories_count' => function ($query) {
                $query->where('status', 'published');
            },
            'alerts',
            'claimedAlerts as fixes_count' => function ($query) {
                $query->where('status', 'fixed');
            },
        ]);

        $tab = request()->string('tab')->toString();

        if (! in_array($tab, ['stories', 'alerts'], true)) {
            $tab = 'stories';
        }

        $stories = $user->posts()
            ->where('status', 'published')
            ->latest('published_at')
            ->latest('id')
            ->limit(18)
            ->get();

        $alerts = $user->alerts()
            ->latest('created_at')
            ->latest('id')
            ->limit(18)
            ->get();

        $scoreEvents = $user->scoreEvents()
            ->latest()
            ->limit(8)
            ->get();

        return view('profile.show', [
            'profile' => $user,
            'tab' => $tab,
            'stories' => $stories,
            'alerts' => $alerts,
            'scoreEvents' => $scoreEvents,
            'isFollowing' => auth()->id() !== $user->id && auth()->user()->isFollowing($user),
            'isFollowedBy' => auth()->id() !== $user->id && $user->isFollowing(auth()->user()),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except([
            'profile_image',
            'cover_image',
            'remove_profile_image',
            'remove_cover_image',
        ]));

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

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
