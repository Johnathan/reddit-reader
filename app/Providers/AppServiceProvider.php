<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // These composers run for '*' — every partial, so dozens of times on a
        // feed page — hence the memoization. It hangs off the request rather
        // than once(), which caches for the life of the PROCESS: fine under
        // php-fpm, but it serves stale data under Octane and across requests
        // in a single test.
        $perRequest = function (string $key, callable $resolve) {
            $bag = request()->attributes;

            if (! $bag->has($key)) {
                $bag->set($key, Auth::check() ? $resolve(Auth::user()) : null);
            }

            return $bag->get($key);
        };

        View::composer('*', function ($view) use ($perRequest) {
            // The current user's feeds, so the "add to feed" control can render
            // on any subreddit/post row.
            $view->with('myFeeds', $perRequest(
                'app.myFeeds',
                fn ($user) => $user->feeds()->orderBy('name')->get(),
            ) ?? collect());

            // The desktop sidebar's community list.
            $view->with('mySubs', $perRequest(
                'app.mySubs',
                fn ($user) => $user->subscriptions()->orderBy('subreddit')->get(),
            ) ?? collect());

            // The reading-list dock floats on every page...
            $view->with('myReadingList', $perRequest(
                'app.myReadingList',
                fn ($user) => $user->readingList()->orderBy('read_at')->orderByDesc('created_at')->get(),
            ) ?? collect());

            // ...and every post card needs to know if it's already on it.
            $view->with('myReadingListIds', $perRequest(
                'app.myReadingListIds',
                fn ($user) => array_flip($user->readingList()->pluck('post_id')->all()),
            ) ?? []);

            // Which posts/comments are already in the archive, and where they
            // can be filed.
            $view->with('myArchivedIds', $perRequest(
                'app.myArchivedIds',
                fn ($user) => array_flip($user->savedItems()->pluck('reddit_id')->all()),
            ) ?? []);

            $view->with('myFolders', $perRequest(
                'app.myFolders',
                fn ($user) => $user->folders()->orderBy('name')->get(),
            ) ?? collect());
        });
    }
}
