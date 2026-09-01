<?php

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

    Route::get('categories/{category:slug}', [PostController::class, 'byCategory'])
        ->name('categories.show');

    Route::get('authors/{user}', [PostController::class, 'byAuthor'])
        ->name('authors.show');

    Route::post('posts/{post}/likes', [LikeController::class, 'store'])
        ->name('posts.likes.store');

    Route::delete('posts/{post}/likes', [LikeController::class, 'destroy'])
        ->name('posts.likes.destroy');

    Route::get('posts/{post}/comments', [CommentController::class, 'index'])
        ->name('posts.comments.index');

    Route::post('posts/{post}/comments', [CommentController::class, 'store'])
        ->name('posts.comments.store');

    Route::put('comments/{comment}', [CommentController::class, 'update'])
        ->name('comments.update');

    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
        ->name('comments.destroy');

});

require __DIR__.'/auth.php';
