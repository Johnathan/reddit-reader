<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HackerNewsController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageProxyController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReadingListController;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\SeenController;
use App\Http\Controllers\SourceController;
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

    // Save-for-later. Everything but the index answers JSON so saving from the
    // feed never navigates.
    Route::get('/reading-list', [ReadingListController::class, 'index'])->name('reading.index');
    Route::get('/reading-list/read', [ReadingListController::class, 'reader'])->name('reading.reader');
    Route::post('/reading-list', [ReadingListController::class, 'store'])->name('reading.store');
    Route::delete('/reading-list', [ReadingListController::class, 'clear'])->name('reading.clear');
    Route::get('/reading-list/{postId}/content', [ReadingListController::class, 'content'])->name('reading.content');
    Route::delete('/reading-list/{postId}', [ReadingListController::class, 'destroy'])->name('reading.destroy');
    Route::post('/reading-list/{postId}/read', [ReadingListController::class, 'toggleRead'])->name('reading.read');
    Route::post('/reading-list/{postId}/mark-read', [ReadingListController::class, 'markRead'])->name('reading.markRead');

    // The archive: posts and comments kept permanently, with their content
    // copied in so deleted originals stay readable.
    Route::get('/saved', [SavedController::class, 'index'])->name('saved.index');
    Route::post('/saved', [SavedController::class, 'store'])->name('saved.store');
    Route::patch('/saved/{savedItem}', [SavedController::class, 'update'])->name('saved.update');
    Route::delete('/saved/{redditId}', [SavedController::class, 'destroy'])->name('saved.destroy');
    Route::post('/saved/folders', [SavedController::class, 'storeFolder'])->name('saved.folders.store');
    Route::delete('/saved/folders/{folder}', [SavedController::class, 'destroyFolder'])->name('saved.folders.destroy');

    // Hacker News threads and RSS-source articles, both part of the unified
    // home timeline.
    Route::get('/hn/{id}', [HackerNewsController::class, 'item'])->name('hn.item');
    Route::get('/news/{source}/{id}/body', [ArticleController::class, 'body'])->name('article.body');
    Route::get('/news/{source}/{id}', [ArticleController::class, 'show'])->name('article.show');

    // Turn a whole source (Reddit / HN / News) on or off in the home timeline.
    Route::post('/sources/{source}/toggle', [SourceController::class, 'toggle'])->name('sources.toggle');

    // Read-tracking writes that don't come from opening a page (expando
    // opens), plus the one-time localStorage import.
    Route::post('/seen', [SeenController::class, 'store'])->name('seen.store');
    Route::post('/seen/import', [SeenController::class, 'import'])->name('seen.import');

    Route::get('/subreddits/suggest', [SubscriptionController::class, 'suggest'])->name('subs.suggest');

    Route::get('/subs', [SubscriptionController::class, 'index'])->name('subs.index');
    Route::post('/subs', [SubscriptionController::class, 'store'])->name('subs.store');
    Route::delete('/subs/{subreddit}', [SubscriptionController::class, 'destroy'])->name('subs.destroy');

    // HTML fragment: a sub's first few posts, unfolded inline on Explore.
    Route::get('/r/{subreddit}/preview', [SubredditController::class, 'preview'])->name('subreddit.preview');

    Route::get('/r/{subreddit}', [SubredditController::class, 'show'])->name('subreddit.show');
});

// The one page a logged-out visitor can see: a shared post link. The
// controller serves guests a stripped read-only page (no app chrome, no
// account data) and the owner the full app. Everything else stays behind
// auth, so a share link never becomes a way to browse.
Route::get('/r/{subreddit}/comments/{id}/{slug?}', [PostController::class, 'show'])->name('posts.show');

// Geo-blocked media (imgur pulled out of the UK) fetched server-side. Public
// so shared post pages can render their media for logged-out visitors; the
// signature means only URLs this app generated can be requested, so it can't
// be used as an open proxy.
Route::get('/img', ImageProxyController::class)->middleware('signed')->name('image.proxy');

require __DIR__."/auth.php";

