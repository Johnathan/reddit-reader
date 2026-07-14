<?php

use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SubredditController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/explore', [ExploreController::class, 'index'])->name('explore');

    // Custom feeds (local multireddits): named groups of subs, no account needed.
    Route::get('/feeds', [FeedController::class, 'index'])->name('feeds.index');
    Route::post('/feeds', [FeedController::class, 'store'])->name('feeds.store');
    Route::get('/f/{slug}', [FeedController::class, 'show'])->name('feeds.show');
    Route::delete('/feeds/{feed}', [FeedController::class, 'destroy'])->name('feeds.destroy');
    Route::post('/feeds/{feed}/subs', [FeedController::class, 'addSubreddit'])->name('feeds.subs.store');
    Route::delete('/feeds/{feed}/subs/{subreddit}', [FeedController::class, 'removeSubreddit'])->name('feeds.subs.destroy');

    Route::get('/subs', [SubscriptionController::class, 'index'])->name('subs.index');
    Route::post('/subs', [SubscriptionController::class, 'store'])->name('subs.store');
    Route::delete('/subs/{subreddit}', [SubscriptionController::class, 'destroy'])->name('subs.destroy');

    Route::get('/r/{subreddit}/comments/{id}/{slug?}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/r/{subreddit}', [SubredditController::class, 'show'])->name('subreddit.show');
});

require __DIR__."/auth.php";
