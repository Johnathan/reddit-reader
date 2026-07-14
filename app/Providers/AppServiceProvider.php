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
        // Make the current user's feeds available everywhere so the
        // "add to feed" control can render on any subreddit/post row. The
        // composer runs '*' (including partials), so memoize per request —
        // otherwise the home feed would re-run this query for every card.
        View::composer('*', function ($view) {
            $view->with('myFeeds', once(fn () => Auth::check()
                ? Auth::user()->feeds()->orderBy('name')->get()
                : collect()));
        });
    }
}
