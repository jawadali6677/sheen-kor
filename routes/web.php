<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {

    Route::resource('posts', PostController::class);
    Route::resource('alerts', AlertController::class);

    Route::get('categories/{category:slug}', [PostController::class, 'byCategory'])
        ->name('categories.show');

    Route::get('authors/{user}', [PostController::class, 'byAuthor'])
        ->name('authors.show');

    Route::post('posts/{post}/likes', [LikeController::class, 'storePost'])
        ->name('posts.likes.store');

    Route::delete('posts/{post}/likes', [LikeController::class, 'destroyPost'])
        ->name('posts.likes.destroy');

    Route::get('posts/{post}/comments', [CommentController::class, 'indexPost'])
        ->name('posts.comments.index');

    Route::post('posts/{post}/comments', [CommentController::class, 'storePost'])
        ->name('posts.comments.store');

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

});

require __DIR__.'/auth.php';
