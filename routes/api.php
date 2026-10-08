<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\MarketListingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProfileController;
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
        Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
