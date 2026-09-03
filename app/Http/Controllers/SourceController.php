<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    /**
     * The toggleable top-level sources on the home timeline. 'news' is the
     * umbrella for every configured RSS feed, so adding feeds later doesn't
     * add switches.
     */
    public const SOURCES = ['reddit' => 'Reddit', 'hn' => 'Hacker News', 'news' => 'News'];

    /**
     * Flip one source on/off for the current user, then bounce back to the
     * feed so it re-renders with the new mix.
     */
    public function toggle(Request $request, string $source): RedirectResponse
    {
        abort_unless(array_key_exists($source, self::SOURCES), 404);

        $user = $request->user();
        $disabled = $user->disabledSources();

        $disabled = in_array($source, $disabled, true)
            ? array_values(array_diff($disabled, [$source]))
            : [...$disabled, $source];

        $user->disabled_sources = $disabled;
        $user->save();

        // Back to wherever the toggle was flipped, keeping its sort/query —
        // a bare route('home') would reset a top/week feed to hot.
        return back(fallback: route('home'));
    }
}
