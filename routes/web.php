<?php

use App\Http\Controllers\AdEventController;
use App\Http\Controllers\Admin\GreenTickController as AdminGreenTickController;
use App\Http\Controllers\Admin\ListingPromotionController as AdminListingPromotionController;
use App\Http\Controllers\Admin\MarketListingController as AdminMarketListingController;
use App\Http\Controllers\Admin\MonetizationController as AdminMonetizationController;
use App\Http\Controllers\Admin\MonetizationDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PostBoostController as AdminPostBoostController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationParticipantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GreenTickController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ListingPromotionController;
use App\Http\Controllers\MarketListingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostBoostController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RewardedAdController;
use App\Http\Controllers\VideoInterstitialController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('posts.index');
    }

    return view('welcome');
})->name('home');

Route::view('/about', 'about')->name('about');

Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');

Route::get('/tips', function () {
    $category = Category::query()->firstOrCreate(
        ['slug' => 'tips'],
        [
            'name' => 'Tips',
            'description' => 'Practical advice for cleaner, greener everyday life.',
            'status' => true,
        ],
    );

    abort_unless($category->status, 404);

    return app(PostController::class)->byCategory(request(), $category);
})->name('tips.index');

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::get('/market', [MarketListingController::class, 'index'])->name('market.index');
Route::post('/ads/{advertisement}/impressions', [AdEventController::class, 'storeImpression'])
    ->middleware('throttle:60,1')
    ->name('ads.impressions.store');
Route::get('/ads/{advertisement}/click', [AdEventController::class, 'click'])
    ->middleware('throttle:60,1')
    ->name('ads.click');
Route::post('/ads/video-interstitials', [VideoInterstitialController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('ads.video-interstitials.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/green-tick', [GreenTickController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('green-tick.store');
    Route::delete('/green-tick/{verification}', [GreenTickController::class, 'destroy'])
        ->name('green-tick.destroy');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('orders.pay');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::post('/rewarded-ads', [RewardedAdController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('rewarded-ads.store');
    Route::post('/rewarded-ads/{rewardedAdSession}/complete', [RewardedAdController::class, 'complete'])
        ->middleware('throttle:10,1')
        ->name('rewarded-ads.complete');
    Route::post('/rewarded-ads/{rewardedAdSession}/fail', [RewardedAdController::class, 'fail'])
        ->middleware('throttle:10,1')
        ->name('rewarded-ads.fail');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'update'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/users/{user}/follow', [FollowController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('users.follow.store');
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('users.follow.destroy');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::patch('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('/posts/{post:slug}/boost', [PostBoostController::class, 'create'])->name('posts.boost.create');
    Route::post('/posts/{post:slug}/boost', [PostBoostController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('posts.boost.store');
    Route::delete('/post-boosts/{boost}', [PostBoostController::class, 'destroy'])->name('posts.boost.destroy');

    Route::get('/alerts/create', [AlertController::class, 'create'])->name('alerts.create');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('alerts.edit');
    Route::put('/alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
    Route::patch('/alerts/{alert}', [AlertController::class, 'update']);
    Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');

    Route::get('/market/create', [MarketListingController::class, 'create'])->name('market.create');
    Route::post('/market', [MarketListingController::class, 'store'])->name('market.store');
    Route::get('/market/mine', [MarketListingController::class, 'mine'])->name('market.mine');
    Route::get('/market/{listing}/promote', [ListingPromotionController::class, 'create'])->name('market.promote.create');
    Route::post('/market/{listing}/promote', [ListingPromotionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('market.promote.store');
    Route::delete('/listing-promotions/{promotion}', [ListingPromotionController::class, 'destroy'])->name('market.promote.destroy');
    Route::get('/market/{listing}/edit', [MarketListingController::class, 'edit'])->name('market.edit');
    Route::put('/market/{listing}', [MarketListingController::class, 'update'])->name('market.update');
    Route::patch('/market/{listing}', [MarketListingController::class, 'update']);
    Route::delete('/market/{listing}', [MarketListingController::class, 'destroy'])->name('market.destroy');
    Route::post('/market/{listing}/contact', [MarketListingController::class, 'contact'])->name('market.contact');
    Route::post('/market/{listing}/sold', [MarketListingController::class, 'markSold'])->name('market.sold');
    Route::post('/market/{listing}/exchanged', [MarketListingController::class, 'markExchanged'])->name('market.exchanged');
    Route::post('/market/{listing}/donated', [MarketListingController::class, 'markDonated'])->name('market.donated');
    Route::post('/market/{listing}/close', [MarketListingController::class, 'close'])->name('market.close');
    Route::post('/market/{listing}/report', [MarketListingController::class, 'report'])
        ->middleware('throttle:60,1')
        ->name('market.report');

    Route::post('posts/{post}/likes', [LikeController::class, 'storePost'])
        ->name('posts.likes.store');

    Route::delete('posts/{post}/likes', [LikeController::class, 'destroyPost'])
        ->name('posts.likes.destroy');

    Route::get('posts/{post}/comments', [CommentController::class, 'indexPost'])
        ->name('posts.comments.index');

    Route::post('posts/{post}/comments', [CommentController::class, 'storePost'])
        ->name('posts.comments.store');

    Route::post('alerts/{alert}/take-action', [AlertController::class, 'takeAction'])
        ->name('alerts.take-action');

    Route::post('alerts/{alert}/mark-fixed', [AlertController::class, 'markFixed'])
        ->name('alerts.mark-fixed');

    Route::post('alerts/{alert}/likes', [LikeController::class, 'storeAlert'])
        ->name('alerts.likes.store');

    Route::delete('alerts/{alert}/likes', [LikeController::class, 'destroyAlert'])
        ->name('alerts.likes.destroy');

    Route::get('alerts/{alert}/comments', [CommentController::class, 'indexAlert'])
        ->name('alerts.comments.index');

    Route::post('alerts/{alert}/comments', [CommentController::class, 'storeAlert'])
        ->name('alerts.comments.store');

    Route::put('comments/{comment}', [CommentController::class, 'update'])
        ->name('comments.update');

    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
        ->name('comments.destroy');

    Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::post('/messages/direct', [ConversationController::class, 'storeDirect'])->name('messages.direct.store');
    Route::get('/messages/groups/create', [ConversationController::class, 'createGroup'])->name('messages.groups.create');
    Route::post('/messages/groups', [ConversationController::class, 'storeGroup'])->name('messages.groups.store');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::get('/messages/{conversation}/messages', [MessageController::class, 'index'])->name('messages.messages.index');
    Route::post('/messages/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('messages.messages.store');
    Route::post('/messages/{conversation}/participants', [ConversationParticipantController::class, 'store'])
        ->name('messages.participants.store');
    Route::delete('/messages/{conversation}/participants/{user}', [ConversationParticipantController::class, 'remove'])
        ->name('messages.participants.remove');
    Route::delete('/messages/{conversation}/participants', [ConversationParticipantController::class, 'destroy'])
        ->name('messages.participants.destroy');
    Route::delete('/messages/{conversation}', [ConversationController::class, 'destroy'])
        ->name('messages.destroy');
});

Route::get('/posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');
Route::get('/alerts/{alert:slug}', [AlertController::class, 'show'])->name('alerts.show');
Route::get('/market/{listing:slug}', [MarketListingController::class, 'show'])->name('market.show');
Route::get('categories/{category:slug}', [PostController::class, 'byCategory'])->name('categories.show');
Route::get('/users/{user:username}', [ProfileController::class, 'show'])->name('users.show');
Route::get('authors/{user:username}', [ProfileController::class, 'show'])->name('authors.show');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'permission:posts.moderate'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('posts/{post:id}', [AdminPostController::class, 'show'])->name('posts.show');
    Route::post('posts/{post:id}/publish', [AdminPostController::class, 'publish'])->name('posts.publish');
    Route::post('posts/{post:id}/pending', [AdminPostController::class, 'pending'])->name('posts.pending');
    Route::post('posts/{post:id}/reject', [AdminPostController::class, 'reject'])->name('posts.reject');
    Route::delete('posts/{post:id}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
});

Route::middleware(['auth', 'permission:market.moderate'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('market', [AdminMarketListingController::class, 'index'])->name('market.index');
    Route::get('market/{listing:id}', [AdminMarketListingController::class, 'show'])->name('market.show');
    Route::post('market/{listing:id}/publish', [AdminMarketListingController::class, 'publish'])->name('market.publish');
    Route::post('market/{listing:id}/pending', [AdminMarketListingController::class, 'pending'])->name('market.pending');
    Route::post('market/{listing:id}/reject', [AdminMarketListingController::class, 'reject'])->name('market.reject');
    Route::post('market/{listing:id}/reports/review', [AdminMarketListingController::class, 'reviewReports'])->name('market.reports.review');
    Route::delete('market/{listing:id}', [AdminMarketListingController::class, 'destroy'])->name('market.destroy');
});

Route::middleware(['auth', 'permission:users.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user:id}', [AdminUserController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', 'permission:roles.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class)->except(['show']);
});

Route::middleware(['auth', 'permission:monetization.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('monetization', [AdminMonetizationController::class, 'index'])->name('monetization.index');
    Route::get('monetization/dashboard', [MonetizationDashboardController::class, 'index'])->name('monetization.dashboard');
    Route::patch('monetization/settings', [AdminMonetizationController::class, 'updateSettings'])->name('monetization.settings.update');
    Route::get('monetization/packages/{package:id}/edit', [AdminMonetizationController::class, 'editPackage'])->name('monetization.packages.edit');
    Route::patch('monetization/packages/{package:id}', [AdminMonetizationController::class, 'updatePackage'])->name('monetization.packages.update');
    Route::patch('monetization/advertisements/{advertisement:id}', [AdminMonetizationController::class, 'updateAdvertisement'])->name('monetization.advertisements.update');
    Route::get('monetization/orders', [AdminOrderController::class, 'index'])->name('monetization.orders.index');
    Route::get('monetization/orders/{order}', [AdminOrderController::class, 'show'])->name('monetization.orders.show');
    Route::post('monetization/orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('monetization.orders.mark-paid');
    Route::post('monetization/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('monetization.orders.cancel');
    Route::get('monetization/green-ticks', [AdminGreenTickController::class, 'index'])->name('monetization.green-ticks.index');
    Route::post('monetization/green-ticks/grant', [AdminGreenTickController::class, 'grant'])->name('monetization.green-ticks.grant');
    Route::get('monetization/green-ticks/{verification}', [AdminGreenTickController::class, 'show'])->name('monetization.green-ticks.show');
    Route::post('monetization/green-ticks/{verification}/approve', [AdminGreenTickController::class, 'approve'])->name('monetization.green-ticks.approve');
    Route::post('monetization/green-ticks/{verification}/reject', [AdminGreenTickController::class, 'reject'])->name('monetization.green-ticks.reject');
    Route::get('monetization/boosts', [AdminPostBoostController::class, 'index'])->name('monetization.boosts.index');
    Route::post('monetization/boosts/grant', [AdminPostBoostController::class, 'grant'])->name('monetization.boosts.grant');
    Route::get('monetization/boosts/{boost}', [AdminPostBoostController::class, 'show'])->name('monetization.boosts.show');
    Route::post('monetization/boosts/{boost}/activate', [AdminPostBoostController::class, 'activate'])->name('monetization.boosts.activate');
    Route::post('monetization/boosts/{boost}/cancel', [AdminPostBoostController::class, 'cancel'])->name('monetization.boosts.cancel');
    Route::get('monetization/promotions', [AdminListingPromotionController::class, 'index'])->name('monetization.promotions.index');
    Route::post('monetization/promotions/grant', [AdminListingPromotionController::class, 'grant'])->name('monetization.promotions.grant');
    Route::get('monetization/promotions/{promotion}', [AdminListingPromotionController::class, 'show'])->name('monetization.promotions.show');
    Route::post('monetization/promotions/{promotion}/activate', [AdminListingPromotionController::class, 'activate'])->name('monetization.promotions.activate');
    Route::post('monetization/promotions/{promotion}/cancel', [AdminListingPromotionController::class, 'cancel'])->name('monetization.promotions.cancel');
});

require __DIR__.'/auth.php';
