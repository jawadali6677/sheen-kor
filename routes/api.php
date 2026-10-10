<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\LikeController;
use App\Http\Controllers\Api\V1\MarketListingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\QualifiedPostViewController;
use App\Http\Middleware\AuthenticateOptionalApiToken;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:api-register')
        ->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-forgot')
        ->name('auth.forgot-password');

    Route::middleware([AuthenticateOptionalApiToken::class, EnsureApiUserIsActive::class])->group(function (): void {
        Route::get('categories', [PostController::class, 'categories'])->name('categories.index');
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');
        Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::get('alerts/{alert:slug}', [AlertController::class, 'show'])->name('alerts.show');
        Route::get('market/categories', [MarketListingController::class, 'categories'])->name('market.categories');
        Route::get('market/mine', [MarketListingController::class, 'mine'])
            ->middleware(['auth:sanctum', 'ability:mobile', EnsureApiUserIsActive::class])
            ->name('market.mine');
        Route::get('market', [MarketListingController::class, 'index'])->name('market.index');
        Route::get('market/{listing:slug}', [MarketListingController::class, 'show'])->name('market.show');
        Route::get('users/{user:username}', [ProfileController::class, 'show'])->name('users.show');
        Route::get('users/{user:username}/stories', [ProfileController::class, 'stories'])->name('users.stories');
        Route::get('users/{user:username}/alerts', [ProfileController::class, 'alerts'])->name('users.alerts');
        Route::get('users/{user:username}/fixes', [ProfileController::class, 'fixes'])->name('users.fixes');
        Route::get('users/{user:username}/listings', [ProfileController::class, 'listings'])->name('users.listings');
    });

    Route::middleware(['auth:sanctum', 'ability:mobile', EnsureApiUserIsActive::class])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('auth/password', [AuthController::class, 'updatePassword'])->name('auth.password');
        Route::delete('auth/account', [AuthController::class, 'destroy'])->name('auth.account.destroy');
        Route::post('posts/qualified-views', [QualifiedPostViewController::class, 'store'])
            ->middleware('throttle:qualified-views')
            ->name('posts.qualified-views');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::post('posts/{post}/likes', [LikeController::class, 'storePost'])->name('posts.likes.store');
        Route::delete('posts/{post}/likes', [LikeController::class, 'destroyPost'])->name('posts.likes.destroy');
        Route::get('posts/{post}/comments', [CommentController::class, 'indexPost'])->name('posts.comments.index');
        Route::post('posts/{post}/comments', [CommentController::class, 'storePost'])->name('posts.comments.store');
        Route::get('posts/{post}/moderation-status', [PostController::class, 'moderationStatus'])->name('posts.moderation-status');
        Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('alerts', [AlertController::class, 'store'])->name('alerts.store');
        Route::patch('alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
        Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
        Route::post('alerts/{alert}/likes', [LikeController::class, 'storeAlert'])->name('alerts.likes.store');
        Route::delete('alerts/{alert}/likes', [LikeController::class, 'destroyAlert'])->name('alerts.likes.destroy');
        Route::get('alerts/{alert}/comments', [CommentController::class, 'indexAlert'])->name('alerts.comments.index');
        Route::post('alerts/{alert}/comments', [CommentController::class, 'storeAlert'])->name('alerts.comments.store');
        Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('users/{user}/follow', [FollowController::class, 'store'])->name('users.follow.store');
        Route::delete('users/{user}/follow', [FollowController::class, 'destroy'])->name('users.follow.destroy');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('alerts/{alert}/take-action', [AlertController::class, 'takeAction'])->name('alerts.take-action');
        Route::post('alerts/{alert}/mark-fixed', [AlertController::class, 'markFixed'])->name('alerts.mark-fixed');
        Route::post('market', [MarketListingController::class, 'store'])->name('market.store');
        Route::patch('market/{listing}', [MarketListingController::class, 'update'])->name('market.update');
        Route::delete('market/{listing}', [MarketListingController::class, 'destroy'])->name('market.destroy');
        Route::post('market/{listing}/sold', [MarketListingController::class, 'sold'])->name('market.sold');
        Route::post('market/{listing}/exchanged', [MarketListingController::class, 'exchanged'])->name('market.exchanged');
        Route::post('market/{listing}/donated', [MarketListingController::class, 'donated'])->name('market.donated');
        Route::post('market/{listing}/close', [MarketListingController::class, 'close'])->name('market.close');
        Route::post('market/{listing}/report', [MarketListingController::class, 'report'])->name('market.report');
        Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
