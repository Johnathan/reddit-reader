<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Feed;
use App\Services\Reddit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Custom feeds — local multireddits. A feed is a named set of subreddits the
 * user views as one combined listing (/r/a+b+c). Entirely local; no account.
 */
class FeedController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    public function index(Request $request): View
    {
        $feeds = $request->user()->feeds()->with('subreddits')->orderBy('name')->get();

        return view('feeds.index', ['feeds' => $feeds]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $feed = $request->user()->feeds()->create([
            'name' => $validated['name'],
            'slug' => Feed::uniqueSlugFor($request->user()->id, $validated['name']),
        ]);

        // 303 so Turbo follows the redirect as a GET visit to the new feed.
        return redirect()->route('feeds.show', $feed->slug, 303)
            ->with('status', "Created feed “{$feed->name}”. Add some subreddits to it.");
    }

    /**
     * View a feed's combined listing, with sort + pagination like the home feed.
     */
    public function show(Request $request, string $slug): View
    {
        $feed = $request->user()->feeds()->with('subreddits')->where('slug', $slug)->firstOrFail();

        $sort = $request->string('sort', 'hot')->toString();
        $subreddits = $feed->subredditNames();

        $error = null;
        $posts = [];
        $after = null;

        if (! empty($subreddits)) {
            try {
                $listing = $this->reddit->homeListing($subreddits, $sort, [
                    'after' => $request->query('after'),
                    't' => $request->query('t'),
                ]);

                $posts = $listing['data']['children'] ?? [];
                $after = $listing['data']['after'] ?? null;
            } catch (RedditException $e) {
                $error = $e->getMessage();
            }
        }

        return view('feeds.show', [
            'feed' => $feed,
            'subreddits' => $subreddits,
            'posts' => $posts,
            'sort' => $sort,
            'after' => $after,
            'error' => $error,
        ]);
    }

    public function destroy(Request $request, Feed $feed): RedirectResponse|JsonResponse
    {
        $this->authorizeFeed($request, $feed);

        $name = $feed->name;
        $feed->delete();

        $message = "Deleted feed “{$name}”.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('feeds.index')->with('status', $message);
    }

    public function addSubreddit(Request $request, Feed $feed): RedirectResponse|JsonResponse
    {
        $this->authorizeFeed($request, $feed);

        $validated = $request->validate([
            'subreddit' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{2,21}$/'],
        ]);

        $item = $feed->subreddits()->firstOrCreate(['subreddit' => strtolower($validated['subreddit'])]);

        $message = "Added r/{$validated['subreddit']} to “{$feed->name}”.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'html' => view('feeds.partials.chip', ['feed' => $feed, 'fs' => $item])->render(),
            ]);
        }

        return back()->with('status', $message);
    }

    public function removeSubreddit(Request $request, Feed $feed, string $subreddit): RedirectResponse|JsonResponse
    {
        $this->authorizeFeed($request, $feed);

        $feed->subreddits()->where('subreddit', strtolower($subreddit))->delete();

        $message = "Removed r/{$subreddit} from “{$feed->name}”.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * A user may only touch their own feeds.
     */
    protected function authorizeFeed(Request $request, Feed $feed): void
    {
        abort_unless($feed->user_id === $request->user()->id, 404);
    }
}
