<?php

namespace App\Http\Controllers;

use App\Actions\CreateMonetizationOrder;
use App\Actions\StartStripeCheckout;
use App\Enums\MonetizationPackageType;
use App\Enums\PostBoostStatus;
use App\Models\MonetizationPackage;
use App\Models\Post;
use App\Models\PostBoost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostBoostController extends Controller
{
    public function create(Post $post): View
    {
        $this->authorize('boost', $post);

        return view('posts.boost', [
            'post' => $post,
            'packages' => $this->enabledBoostPackages(),
            'openBoost' => $post->boosts()
                ->with('order')
                ->where(function ($query): void {
                    $query->currentlyActive()
                        ->orWhere('status', PostBoostStatus::Pending);
                })
                ->latest('id')
                ->first(),
        ]);
    }

    public function store(Request $request, Post $post, CreateMonetizationOrder $createOrder, StartStripeCheckout $startStripeCheckout): RedirectResponse
    {
        $this->authorize('boost', $post);

        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query): void {
                    $query->where('type', MonetizationPackageType::PostBoost->value)
                        ->where('is_enabled', true);
                }),
            ],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);
        $maxDays = monetization_setting('boost_max_days', 30);

        if ($package->duration_days === null || $package->duration_days < 1 || $package->duration_days > $maxDays) {
            return back()->with('error', 'That boost package is not available.');
        }

        $order = $createOrder->handle($request->user(), $package, post: $post);

        if ($order === null) {
            return back()->with('error', 'This post already has a pending or active boost, or it cannot be boosted.');
        }

        return $startStripeCheckout->redirect($order);
    }

    public function destroy(Request $request, PostBoost $boost): RedirectResponse
    {
        abort_unless($request->user()?->id === $boost->user_id, 403);
        abort_unless($boost->status === PostBoostStatus::Pending, 403);

        $boost->order?->cancelIfPending();

        $boost->forceFill([
            'status' => PostBoostStatus::Cancelled,
        ])->save();

        return redirect()
            ->route('posts.show', $boost->post)
            ->with('success', 'The boost request was cancelled.');
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function enabledBoostPackages()
    {
        $maxDays = monetization_setting('boost_max_days', 30);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->where('is_enabled', true)
            ->where('duration_days', '<=', $maxDays)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
