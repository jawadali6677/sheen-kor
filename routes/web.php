<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationParticipantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/users/{user}', [ProfileController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/follow', [FollowController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('users.follow.store');
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('users.follow.destroy');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    Route::resource('posts', PostController::class);
    Route::resource('alerts', AlertController::class);

    Route::get('categories/{category:slug}', [PostController::class, 'byCategory'])
        ->name('categories.show');

    Route::get('authors/{user}', [ProfileController::class, 'show'])->name('authors.show');

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

Route::middleware(['auth', 'permission:users.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', 'permission:roles.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class)->except(['show']);
});

require __DIR__.'/auth.php';
