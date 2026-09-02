<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-tracking writes that don't come from opening a page: the feed's inline
 * expandos, plus a one-time import of the short-lived localStorage tracker
 * this replaced.
 */
class SeenController extends Controller
{
    /** 'reddit:abc12' / 'hn:41231' / 'rss:bbcni:d41d8cd9' */
    protected const KEY_PATTERN = '/^(reddit|hn|rss)[A-Za-z0-9:_-]{1,110}$/';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:'.self::KEY_PATTERN],
            'comments' => ['nullable', 'integer', 'min:0'],
        ]);

        $request->user()->markSeen($data['key'], $data['comments'] ?? null);

        return response()->json(['ok' => true]);
    }

    /**
     * Bulk import from the retired localStorage store: {key: {t: ms, c: n}}.
     * Existing rows win — the server may already have fresher state from
     * another device.
     */
    public function import(Request $request): JsonResponse
    {
        $items = $request->input('items');

        if (! is_array($items)) {
            return response()->json(['ok' => false], 422);
        }

        $user = $request->user();
        $existing = array_flip($user->seenItems()->pluck('seen_key')->all());
        $imported = 0;

        foreach (array_slice($items, 0, 5000, preserve_keys: true) as $key => $rec) {
            if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key) || isset($existing[$key])) {
                continue;
            }

            $user->seenItems()->create([
                'seen_key' => $key,
                'comments_seen' => is_numeric($rec['c'] ?? null) ? max(0, (int) $rec['c']) : null,
                'last_seen_at' => is_numeric($rec['t'] ?? null)
                    ? Carbon::createFromTimestampMs((int) $rec['t'])
                    : now(),
            ]);
            $imported++;
        }

        return response()->json(['ok' => true, 'imported' => $imported]);
    }
}
