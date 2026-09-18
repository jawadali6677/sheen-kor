<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MonetizationPackageType;
use App\Enums\Permission;
use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\MonetizationPackage;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\GreenTickReviewResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GreenTickController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending_review', 'active', 'rejected', 'expired', 'cancelled', 'all'], true)) {
            $status = 'pending_review';
        }

        $requests = UserVerification::query()
            ->with(['user', 'package', 'reviewer'])
            ->when($status !== 'all', function ($query) use ($status): void {
                if ($status === UserVerificationStatus::Expired->value) {
                    $query->where(function ($query): void {
                        $query->where('status', UserVerificationStatus::Expired)
                            ->orWhere(function ($query): void {
                                $query->where('status', UserVerificationStatus::Active)
                                    ->where('ends_at', '<=', now());
                            });
                    });

                    return;
                }

                if ($status === UserVerificationStatus::Active->value) {
                    $query->currentlyActive();

                    return;
                }

                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monetization.green-ticks.index', [
            'requests' => $requests,
            'status' => $status,
            'packages' => $this->greenTickPackages(),
            'unpaidGrantsEnabled' => monetization_setting('admin_unpaid_grants_enabled', false),
        ]);
    }

    public function show(UserVerification $verification): View
    {
        $this->authorizeManage();

        $verification->load(['user', 'package', 'reviewer']);

        $eligibility = $verification->user?->greenTickEligibility();

        return view('admin.monetization.green-ticks.show', [
            'verification' => $verification,
            'eligibility' => $eligibility,
        ]);
    }

    public function approve(Request $request, UserVerification $verification): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless($verification->status === UserVerificationStatus::PendingReview, 403);

        $verification->forceFill([
            'reviewed_by' => $request->user()?->id,
            'review_notes' => $request->string('review_notes')->toString() ?: null,
        ]);
        $verification->activateFromSnapshot();

        $verification->user?->notify(new GreenTickReviewResult($verification->fresh()));

        return redirect()
            ->route('admin.monetization.green-ticks.index')
            ->with('success', 'Green Tick was approved.');
    }

    public function reject(Request $request, UserVerification $verification): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless($verification->status === UserVerificationStatus::PendingReview, 403);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $verification->forceFill([
            'status' => UserVerificationStatus::Rejected,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ])->save();

        $verification->user?->notify(new GreenTickReviewResult($verification->fresh()));

        return redirect()
            ->route('admin.monetization.green-ticks.index')
            ->with('success', 'Green Tick request was rejected.');
    }

    public function grant(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        if (! monetization_setting('admin_unpaid_grants_enabled', false)) {
            return back()->with('error', 'Unpaid Green Tick grants are disabled.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query): void {
                    $query->where('type', MonetizationPackageType::GreenTick->value);
                }),
            ],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);

        if ($package->duration_days === null || $package->duration_days < 1) {
            return back()->with('error', 'That Green Tick package has no duration.');
        }

        $result = DB::transaction(function () use ($request, $validated, $package): string {
            $user = User::query()->whereKey($validated['user_id'])->lockForUpdate()->firstOrFail();

            if ($user->hasActiveGreenTick()) {
                return 'active';
            }

            $user->greenTickVerifications()
                ->whereIn('status', [
                    UserVerificationStatus::PendingPayment->value,
                    UserVerificationStatus::PendingReview->value,
                ])
                ->with('order')
                ->get()
                ->each(function (UserVerification $verification) use ($request): void {
                    $verification->order?->cancelIfPending();
                    $verification->forceFill([
                        'status' => UserVerificationStatus::Cancelled,
                        'reviewed_by' => $request->user()?->id,
                        'reviewed_at' => now(),
                        'review_notes' => 'Cancelled because an admin granted Green Tick.',
                    ])->save();
                });

            $verification = $user->greenTickVerifications()->create([
                'package_id' => $package->id,
                'status' => UserVerificationStatus::PendingReview,
                'source' => UserVerificationSource::AdminGrant,
                'package_name' => $package->name,
                'package_slug' => $package->slug,
                'duration_days' => $package->duration_days,
                'price' => $package->price,
                'currency' => $package->currency,
                'reviewed_by' => $request->user()?->id,
                'review_notes' => $validated['review_notes'] ?? 'Unpaid admin grant.',
            ]);

            $verification->activateFromSnapshot();
            $user->notify(new GreenTickReviewResult($verification->fresh()));

            return 'granted';
        });

        if ($result === 'active') {
            return back()->with('error', 'That member already has an active Green Tick.');
        }

        return redirect()
            ->route('admin.monetization.green-ticks.index')
            ->with('success', 'Green Tick was granted.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageMonetization), 403);
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function greenTickPackages()
    {
        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::GreenTick)
            ->orderBy('sort_order')
            ->get();
    }
}
