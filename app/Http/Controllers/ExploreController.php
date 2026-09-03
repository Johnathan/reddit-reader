<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Services\Reddit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Discover new subreddits: what's popular right now, plus subs related to the
 * ones you already follow (mined from their sidebars). All anonymous.
 */
class ExploreController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    public function index(Request $request): View
    {
        $subscribed = $request->user()->subscriptions()->pluck('subreddit')->all();
        $subscribedSet = array_flip($subscribed);

        // Shuffled so the "because you follow" seeds rotate between visits —
        // unshuffled, the four oldest subscriptions were mined forever and
        // the section never changed.
        $seeds = $subscribed;
        shuffle($seeds);

        $error = null;
        $popular = [];

        try {
            $listing = $this->reddit->popularSubreddits(['limit' => 25]);
            $popular = $listing['data']['children'] ?? [];
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        // "Because you follow r/x" — related subs mined from a few of your own
        // subscriptions, with anything you already follow filtered out. Capped
        // so Explore stays fast (each seed is one cached about.json lookup).
        $related = [];
        foreach (array_slice($seeds, 0, 4) as $seed) {
            try {
                $names = $this->reddit->relatedSubreddits($seed);
            } catch (RedditException $e) {
                continue;
            }

            $fresh = array_values(array_filter(
                $names,
                fn ($n) => ! isset($subscribedSet[$n]) && ! isset($related[$n])
            ));

            foreach (array_slice($fresh, 0, 8) as $n) {
                $related[$n] = $seed;
            }
        }

        return view('explore', [
            'popular' => $popular,
            'related' => $related,
            'subscribedSet' => $subscribedSet,
            'error' => $error,
        ]);
    }
}
