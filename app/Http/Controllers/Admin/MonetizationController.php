<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\RewardType;
use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonetizationController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        $settings = MonetizationSetting::query()
            ->whereIn('key', array_keys($this->settingDefinitions()))
            ->get()
            ->keyBy('key');

        $packages = MonetizationPackage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $advertisements = Advertisement::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.monetization.index', [
            'settings' => $settings,
            'settingDefinitions' => $this->settingDefinitions(),
            'packages' => $packages,
            'advertisements' => $advertisements,
        ]);
    }

    public function editPackage(MonetizationPackage $package): View
    {
        $this->authorizeManage();

        return view('admin.monetization.edit-package', [
            'package' => $package,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        if ($request->filled('currency')) {
            $request->merge([
                'currency' => strtoupper((string) $request->input('currency')),
            ]);
        }

        $validated = $request->validate($this->settingRules());

        foreach ($validated as $key => $value) {
            $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            MonetizationSetting::query()
                ->where('key', $key)
                ->update(['value' => $stored]);
        }

        return redirect()
            ->route('admin.monetization.index')
            ->with('success', 'Monetization settings were updated.');
    }

    public function updatePackage(Request $request, MonetizationPackage $package): RedirectResponse
    {
        $this->authorizeManage();

        if ($request->filled('currency')) {
            $request->merge([
                'currency' => strtoupper((string) $request->input('currency')),
            ]);
        }

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $package->forceFill([
            'price' => $validated['price'],
            'currency' => $validated['currency'],
            'duration_days' => $validated['duration_days'],
            'is_enabled' => $validated['is_enabled'],
        ])->save();

        return redirect()
            ->route('admin.monetization.index')
            ->with('success', $package->name.' was updated.');
    }

    public function updateAdvertisement(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'is_feed' => ['required', 'boolean'],
            'is_sidebar' => ['required', 'boolean'],
            'is_video' => ['required', 'boolean'],
            'is_rewarded' => ['required', 'boolean'],
        ]);

        $advertisement->forceFill($validated)->save();

        return redirect()
            ->route('admin.monetization.index')
            ->with('success', $advertisement->title.' was updated.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageMonetization), 403);
    }

    /**
     * @return array<string, array{label: string, type: 'string'|'integer'|'boolean', help: string, min?: int, max?: int}>
     */
    private function settingDefinitions(): array
    {
        return [
            'currency' => [
                'label' => 'Currency',
                'type' => 'string',
                'help' => 'ISO currency code used as the default for monetization.',
            ],
            'green_tick_requires_review' => [
                'label' => 'Green Tick requires admin review',
                'type' => 'boolean',
                'help' => 'When enabled, paid Green Tick requests stay pending until an admin approves them.',
            ],
            'admin_unpaid_grants_enabled' => [
                'label' => 'Allow unpaid admin grants',
                'type' => 'boolean',
                'help' => 'Lets admins grant Green Tick or boosts for testing before a payment gateway is connected.',
            ],
            'eligibility_min_followers' => [
                'label' => 'Minimum followers',
                'type' => 'integer',
                'help' => 'Eligibility only. Not enforced yet.',
                'min' => 0,
                'max' => 1000000,
            ],
            'eligibility_min_published_posts' => [
                'label' => 'Minimum published posts',
                'type' => 'integer',
                'help' => 'Eligibility only. Not enforced yet.',
                'min' => 0,
                'max' => 100000,
            ],
            'eligibility_min_qualified_views_30d' => [
                'label' => 'Minimum qualified views (30 days)',
                'type' => 'integer',
                'help' => 'Eligibility only. Not enforced yet.',
                'min' => 0,
                'max' => 100000000,
            ],
            'feed_ads_posts_enabled' => [
                'label' => 'Feed ads on posts',
                'type' => 'boolean',
                'help' => 'Insert native Sponsored cards into the posts Feed using the interval settings below.',
            ],
            'feed_ads_alerts_enabled' => [
                'label' => 'Feed ads on alerts',
                'type' => 'boolean',
                'help' => 'Insert native Sponsored cards into the alerts Feed using the interval settings below.',
            ],
            'feed_ad_interval' => [
                'label' => 'Feed ad interval',
                'type' => 'integer',
                'help' => 'Organic posts or alerts between native ads. Load-more continues from the overall feed position.',
                'min' => 1,
                'max' => 100,
            ],
            'feed_ad_jitter' => [
                'label' => 'Feed ad jitter',
                'type' => 'integer',
                'help' => 'Allowed variation around the interval. Minimum gap is interval minus jitter (at least 1). Maximum gap is interval plus jitter.',
                'min' => 0,
                'max' => 20,
            ],
            'feed_ad_session_cap' => [
                'label' => 'Feed ad session cap',
                'type' => 'integer',
                'help' => 'Maximum native Feed ads per browser session. Use 0 for no session cap.',
                'min' => 0,
                'max' => 1000,
            ],
            'feed_ad_daily_cap' => [
                'label' => 'Feed ad daily cap',
                'type' => 'integer',
                'help' => 'Maximum native Feed ads per viewer per day. Use 0 for no daily cap.',
                'min' => 0,
                'max' => 10000,
            ],
            'feed_ad_cooldown_seconds' => [
                'label' => 'Feed ad cooldown (seconds)',
                'type' => 'integer',
                'help' => 'Minimum time between native Feed ads for one viewer. Use 0 to allow back-to-back ads while scrolling.',
                'min' => 0,
                'max' => 86400,
            ],
            'sidebar_ads_enabled' => [
                'label' => 'Sidebar ads enabled',
                'type' => 'boolean',
                'help' => 'Desktop right-rail Sponsored cards for signed-in members.',
            ],
            'video_ads_enabled' => [
                'label' => 'Video interstitials enabled',
                'type' => 'boolean',
                'help' => 'After a long enough content video ends on a post or alert page, show at most one interstitial. Never used in the scrolling Feed.',
            ],
            'video_interstitial_min_seconds' => [
                'label' => 'Minimum video duration (seconds)',
                'type' => 'integer',
                'help' => 'Content videos shorter than this never qualify for an interstitial.',
                'min' => 1,
                'max' => 3600,
            ],
            'video_interstitial_cooldown_seconds' => [
                'label' => 'Video interstitial cooldown (seconds)',
                'type' => 'integer',
                'help' => 'Minimum wait after any ad or interstitial before another video interstitial. Use 0 only for testing.',
                'min' => 0,
                'max' => 86400,
            ],
            'video_interstitial_session_cap' => [
                'label' => 'Video interstitial session cap',
                'type' => 'integer',
                'help' => 'Maximum video interstitials per browser session. Use 0 for no session cap.',
                'min' => 0,
                'max' => 1000,
            ],
            'video_interstitial_daily_cap' => [
                'label' => 'Video interstitial daily cap',
                'type' => 'integer',
                'help' => 'Maximum video interstitials per viewer per day. Use 0 for no daily cap.',
                'min' => 0,
                'max' => 10000,
            ],
            'boost_max_days' => [
                'label' => 'Maximum boost duration (days)',
                'type' => 'integer',
                'help' => 'Upper limit for post boost packages.',
                'min' => 1,
                'max' => 3650,
            ],
            'rewarded_ads_enabled' => [
                'label' => 'Rewarded videos enabled',
                'type' => 'boolean',
                'help' => 'Optional opt-in rewarded videos. Completion cannot be verified until an ad provider is connected.',
            ],
            'rewarded_daily_limit' => [
                'label' => 'Rewarded ads daily limit',
                'type' => 'integer',
                'help' => 'Maximum completed rewarded sessions per user per day. Existing default is 1; raise to 3 if you want more opportunities.',
                'min' => 0,
                'max' => 100,
            ],
            'rewarded_cooldown_minutes' => [
                'label' => 'Rewarded ads cooldown (minutes)',
                'type' => 'integer',
                'help' => 'Minimum wait between completed rewarded sessions.',
                'min' => 0,
                'max' => 10080,
            ],
            'rewarded_type' => [
                'label' => 'Rewarded credit type',
                'type' => 'string',
                'help' => 'Stored credit type. Currently only profile_visibility_credit. It does not change scores or Feed ranking yet.',
            ],
            'rewarded_value' => [
                'label' => 'Rewarded credit value',
                'type' => 'integer',
                'help' => 'Server-side credit amount stored on completion. Not accepted from the browser.',
                'min' => 0,
                'max' => 100000,
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function settingRules(): array
    {
        $rules = [];

        foreach ($this->settingDefinitions() as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'boolean' => ['required', 'boolean'],
                'integer' => [
                    'required',
                    'integer',
                    'min:'.($definition['min'] ?? 0),
                    'max:'.($definition['max'] ?? 100000000),
                ],
                'string' => $key === 'currency'
                    ? ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/']
                    : ($key === 'rewarded_type'
                        ? ['required', 'string', Rule::enum(RewardType::class)]
                        : ['required', 'string', 'max:255']),
            };
        }

        return $rules;
    }
}
