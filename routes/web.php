<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SubredditController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/subs', [SubscriptionController::class, 'index'])->name('subs.index');
    Route::post('/subs', [SubscriptionController::class, 'store'])->name('subs.store');
    Route::delete('/subs/{subreddit}', [SubscriptionController::class, 'destroy'])->name('subs.destroy');

    Route::get('/r/{subreddit}/comments/{id}/{slug?}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/r/{subreddit}', [SubredditController::class, 'show'])->name('subreddit.show');
});

require __DIR__."/auth.php";
