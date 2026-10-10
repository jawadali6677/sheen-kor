<?php

namespace App\Http\Controllers;

use App\Actions\DeleteUserAccount;
use App\Actions\UpdateProfile;
use App\Enums\MonetizationPackageType;
use App\Enums\RewardedAdStatus;
use App\Enums\RewardType;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\MonetizationPackage;
use App\Models\RewardedAdSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        abort_unless($user->status || auth()->id() === $user->id, 404);

        $user->load('assignedRole');
        $user->loadExists([
            'greenTickVerifications as has_active_green_tick' => function ($query): void {
                $query->currentlyActive();
            },
        ]);

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

        if (! in_array($tab, ['stories', 'alerts', 'fixes', 'market', 'activity'], true)) {
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

        $claimedAlerts = $user->claimedAlerts()
            ->whereIn('status', ['in_progress', 'fixed'])
            ->latest('action_taken_at')
            ->latest('id')
            ->limit(18)
            ->get();

        $marketListings = $user->marketListings()
            ->where('status', 'published')
            ->latest('published_at')
            ->latest('id')
            ->limit(18)
            ->get();

        $scoreEvents = $user->scoreEvents()
            ->latest()
            ->limit(8)
            ->get();

        $viewer = auth()->user();

        return view('profile.show', [
            'profile' => $user,
            'tab' => $tab,
            'stories' => $stories,
            'alerts' => $alerts,
            'claimedAlerts' => $claimedAlerts,
            'marketListings' => $marketListings,
            'scoreEvents' => $scoreEvents,
            'isFollowing' => $viewer !== null && $viewer->id !== $user->id && $viewer->isFollowing($user),
            'isFollowedBy' => $viewer !== null && $viewer->id !== $user->id && $user->isFollowing($viewer),
        ]);
    }

    public function edit(Request $request): View
    {
        $user = $request->user();
        $rewardType = RewardType::tryFrom((string) monetization_setting('rewarded_type', RewardType::ProfileVisibilityCredit->value))
            ?? RewardType::ProfileVisibilityCredit;

        $greenTickVerification = $user->currentGreenTickVerification();
        $greenTickVerification?->loadMissing('order');

        return view('profile.edit', [
            'user' => $user,
            'greenTickEligibility' => $user->greenTickEligibility(),
            'greenTickVerification' => $greenTickVerification,
            'greenTickPackages' => MonetizationPackage::query()
                ->where('type', MonetizationPackageType::GreenTick)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'rewardedAdsEnabled' => monetization_setting('rewarded_ads_enabled', false),
            'rewardedType' => $rewardType,
            'rewardedValue' => max(0, (int) monetization_setting('rewarded_value', 1)),
            'rewardedDailyLimit' => (int) monetization_setting('rewarded_daily_limit', 1),
            'rewardedSession' => RewardedAdSession::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first(),
            'openRewardedSession' => RewardedAdSession::query()
                ->where('user_id', $user->id)
                ->where('status', RewardedAdStatus::Started)
                ->latest('id')
                ->first(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->user(), $request->validated(), $request);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $deleteUserAccount->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
