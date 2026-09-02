<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Services\ArticleExtractor;
use App\Services\RssSource;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The in-app article reader for RSS sources: resolve the feed item, extract
 * the full text server-side, render it readable. RSS gives us the headline;
 * this is where the "full article" comes from.
 */
class ArticleController extends Controller
{
    public function __construct(protected RssSource $rss, protected ArticleExtractor $extractor) {}

    /**
     * The extracted article as a bare fragment, for the feed card's inline
     * "article" expando. Errors render as fragments too — the card shows
     * whatever comes back in its panel.
     */
    public function body(Request $request, string $source, string $id): View
    {
        abort_if(RssSource::find($source) === null, 404);

        $entry = null;
        $article = null;
        $error = null;

        try {
            $entry = $this->rss->resolve($source, $id);
            $article = $entry ? $this->extractor->extract($entry['url']) : null;

            if ($article === null) {
                $error = $entry === null
                    ? 'This article has dropped out of the feed.'
                    : 'The article could not be extracted.';
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('articles.body', [
            'entry' => $entry,
            'article' => $article,
            'error' => $error,
        ]);
    }

    public function show(Request $request, string $source, string $id): View
    {
        abort_if(RssSource::find($source) === null, 404);

        $entry = null;
        $article = null;
        $error = null;

        try {
            $entry = $this->rss->resolve($source, $id);

            if ($entry === null) {
                $error = 'That article has dropped out of the feed. It may still be on the site.';
            } else {
                $article = $this->extractor->extract($entry['url']);

                if ($article === null) {
                    $error = 'The article could not be extracted.';
                }
            }

            if ($entry !== null) {
                // Reading the article marks it read in every device's feed.
                $request->user()->markSeen('rss:'.$source.':'.$entry['id']);
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('articles.show', [
            'sourceKey' => $source,
            'sourceName' => RssSource::find($source)['name'],
            'entry' => $entry,
            'article' => $article,
            'error' => $error,
        ]);
    }
}
