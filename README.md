# Reddit Reader

Personal single-user Reddit viewer. Laravel 13 + SQLite, server-rendered Blade
(Tailwind via CDN, no build step), passkey-only auth. Wraps Reddit's JSON
endpoints — no posting, read only.

## First run

```sh
php artisan serve
```

1. **Check Reddit connectivity** (no credentials needed — see below):

   ```sh
   php artisan reddit:check
   ```

   Should print the top 5 posts from r/programming.

2. **Passkey**: visit <http://localhost:8000> — you'll be walked to `/setup`,
   which creates your user and registers your first passkey (Touch ID). This
   page only exists while the users table is empty. Enroll more devices later
   at `/settings/passkeys` (it refuses to delete your last passkey).

3. **Subscribe**: `/subs` → search subreddits → subscribe. Home feed is the
   combined listing of everything you're subscribed to.

## Pages

- `/` — home feed (subscribed subs combined), hot/new/top sort, load-more pagination
- `/r/{sub}` — subreddit listing with subscribe/unsubscribe
- `/r/{sub}/comments/{id}` — post + threaded comments (collapse, depth-capped)
- `/subs` — manage subscriptions + subreddit search
- `/settings/passkeys` — enroll/remove passkeys

## How it gets the JSON (the interesting part)

Hitting `reddit.com/r/x/hot.json` from a script gets a **403**. It looks like an
IP ban, but it isn't: Reddit serves logged-out traffic a *JavaScript challenge*
page ("Please wait for verification"). A browser runs the script and gets a
clearance cookie; `curl` can't run JS, so it never gets one and everything 403s.

The challenge is not a real bot-detector. The page ships:

```js
await (async e => e + e)("<hex seed>")
```

— the answer is just the seed concatenated with itself, proof you can execute
JS. `Reddit::solveChallenge()` parses out the seed and the form token, submits
the solution back, and banks the resulting cookies in a `FileCookieJar` at
`storage/app/reddit-cookies.json`. Subsequent requests reuse the cookie; on a
403 it re-solves once and retries. **No API key, no OAuth app, no account.**

If Reddit ever changes the puzzle's shape, the seed regex stops matching and the
app throws `challengeChanged()` loudly rather than silently retrying — that's
the signal to go look at the new challenge script.

`php artisan reddit:check --fresh` throws away the cookies and forces a full
cold solve, which is the fastest way to confirm the whole path still works.

### OAuth (optional, not required)

If `REDDIT_CLIENT_ID` / `REDDIT_CLIENT_SECRET` are ever set in `.env` (a
"script" app from <https://www.reddit.com/prefs/apps>), the client prefers
`oauth.reddit.com` with an app-only token and skips the challenge entirely.
Worth doing only if you start hitting rate limits.

## Notes

- Passkeys are bound to the hostname (`localhost` via `APP_URL`). To use it
  from your phone you'll need an https origin (e.g. Tailscale Serve or a
  Cloudflare tunnel) — set `APP_URL` accordingly and re-enroll passkeys; see
  comments in `config/passkeys.php`.
- Reddit responses are cached (listings 90s, comment pages 300s, sub search 1h).
