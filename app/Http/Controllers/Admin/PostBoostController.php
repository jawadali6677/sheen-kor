<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MonetizationPackageType;
use App\Enums\Permission;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use App\Http\Controllers\Controller;
use App\Models\MonetizationPackage;
use App\Models\Post;
use App\Models\PostBoost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostBoostController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'active', 'expired', 'cancelled', 'all'], true)) {
            $status = 'pending';
        }

        $boosts = PostBoost::query()
            ->with(['user', 'post', 'package', 'activator'])
            ->when($status !== 'all', function ($query) use ($status): void {
                if ($status === PostBoostStatus::Expired->value) {
                    $query->where(function ($query): void {
                        $query->where('status', PostBoostStatus::Expired)
                            ->orWhere(function ($query): void {
                                $query->where('status', PostBoostStatus::Active)
                                    ->where('ends_at', '<=', now());
                            });
                    });

                    return;
                }

                if ($status === PostBoostStatus::Active->value) {
                    $query->currentlyActive();

                    return;
                }

                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monetization.boosts.index', [
            'boosts' => $boosts,
            'status' => $status,
            'packages' => $this->boostPackages(),
            'unpaidGrantsEnabled' => monetization_setting('admin_unpaid_grants_enabled', false),
        ]);
    }

    public function show(PostBoost $boost): View
    {
        $this->authorizeManage();

        $boost->load(['user', 'post', 'package', 'activator']);

        return view('admin.monetization.boosts.show', [
            'boost' => $boost,
        ]);
    }

    public function activate(Request $request, PostBoost $boost): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless($boost->status === PostBoostStatus::Pending, 403);

        if ($boost->post?->status !== 'published') {
            return back()->with('error', 'Only published posts can have an active boost.');
        }

        if ($boost->post->boosts()->currentlyActive()->exists()) {
            return back()->with('error', 'This post already has an active boost.');
        }

        $boost->activateFromSnapshot($request->user());

        return redirect()
            ->route('admin.monetization.boosts.index')
            ->with('success', 'Boost was activated for testing.');
    }

    public function cancel(PostBoost $boost): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(in_array($boost->status, [PostBoostStatus::Pending, PostBoostStatus::Active], true), 403);

        $boost->forceFill([
            'status' => PostBoostStatus::Cancelled,
        ])->save();

        return redirect()
            ->route('admin.monetization.boosts.index')
            ->with('success', 'Boost was cancelled.');
    }

    public function grant(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        if (! monetization_setting('admin_unpaid_grants_enabled', false)) {
            return back()->with('error', 'Unpaid boost grants are disabled.');
        }

        $validated = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query): void {
                    $query->where('type', MonetizationPackageType::PostBoost->value);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);

        if ($package->duration_days === null || $package->duration_days < 1) {
            return back()->with('error', 'That boost package has no duration.');
        }

        $result = DB::transaction(function () use ($request, $validated, $package): string {
            $post = Post::query()->whereKey($validated['post_id'])->lockForUpdate()->firstOrFail();

            if ($post->status !== 'published') {
                return 'unpublished';
            }

            if ($post->hasActiveBoost()) {
                return 'active';
            }

            $post->boosts()
                ->where('status', PostBoostStatus::Pending)
                ->with('order')
                ->get()
                ->each(function (PostBoost $boost): void {
                    $boost->order?->cancelIfPending();
                    $boost->forceFill([
                        'status' => PostBoostStatus::Cancelled,
                        'notes' => 'Cancelled because an admin granted a boost.',
                    ])->save();
                });

            $boost = $post->boosts()->create([
                'user_id' => $post->user_id,
                'package_id' => $package->id,
                'status' => PostBoostStatus::Pending,
                'source' => PostBoostSource::AdminGrant,
                'package_type' => $package->type->value,
                'package_name' => $package->name,
                'package_slug' => $package->slug,
                'duration_days' => $package->duration_days,
                'price' => $package->price,
                'currency' => $package->currency,
                'notes' => $validated['notes'] ?? 'Unpaid admin grant.',
            ]);

            $boost->activateFromSnapshot($request->user());

            return 'granted';
        });

        return match ($result) {
            'unpublished' => back()->with('error', 'Only published posts can be boosted.'),
            'active' => back()->with('error', 'That post already has an active boost.'),
            default => redirect()
                ->route('admin.monetization.boosts.index')
                ->with('success', 'Boost was granted.'),
        };
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageMonetization), 403);
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function boostPackages(): Collection
    {
        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->orderBy('sort_order')
            ->get();
    }
}
