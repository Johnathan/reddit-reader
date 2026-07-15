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
- `/explore` — discover subreddits: what's popular now + "related to your subs"
  (mined from the sidebars of subs you already follow), each with subscribe / add-to-feed
- `/feeds` — custom feeds: named local multireddits
- `/f/{slug}` — a custom feed's combined listing
- `/r/{sub}` — subreddit listing with subscribe/unsubscribe, related-communities strip, add-to-feed
- `/r/{sub}/comments/{id}` — post + threaded comments (collapse, depth-capped)
- `/subs` — manage subscriptions + subreddit search (with live typeahead)
- `/settings/passkeys` — enroll/remove passkeys

## Subreddit autocomplete

The search boxes and the feed "add sub" field have live typeahead backed by
Reddit's own autocomplete endpoint (`subreddit_autocomplete_v2`), proxied
server-side through `GET /subreddits/suggest` so the challenge cookie is applied
and there's no CORS. Suggestions show the icon, name, and member count; results
are cached 5 min and requests are debounced (150 ms) with stale-response
guarding. There's deliberately no local "all subreddits" list — there are
millions and any snapshot is stale instantly; the live endpoint covers them all.

## Custom feeds

Feeds are **local multireddits** — a named set of subreddits you view as one
combined listing (`/r/a+b+c`). They live in this app's database, not on Reddit,
so they need no account. Create one on `/feeds`, then add subs from the feed
page, any subreddit page, or the "+ feed" control on Explore/search rows.

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

## What anonymous access can't do

Everything runs logged-out, which has one hard limit: **age-gated (18+)
subreddits are invisible.** Reddit returns HTTP 200 with an *empty listing* for
them and strips them from search. The only thing that lifts the gate is a
logged-in Reddit account — and Reddit no longer supports the app OAuth flow this
used to lean on, so there's no clean anonymous way in. Left out by design.

## Deployment — https://reddit.1707.network

Served from this Mac through a Cloudflare Tunnel. Nothing is exposed to the
internet directly; Cloudflare terminates TLS at the edge and the tunnel carries
traffic to `localhost:8000`. HTTPS is what makes passkeys work off-device.

| Piece | Where |
|---|---|
| Tunnel | `reddit-reader` (`d77b73d5-b03b-4401-b641-5390566eec96`) |
| Tunnel config | `~/.cloudflared/reddit-reader.yml` |
| DNS | proxied CNAME `reddit.1707.network` → `<uuid>.cfargotunnel.com` |
| App service | `~/Library/LaunchAgents/network.1707.reddit-app.plist` |
| Tunnel service | `~/Library/LaunchAgents/network.1707.reddit-tunnel.plist` |
| Logs | `storage/logs/serve.log`, `storage/logs/tunnel.log` |

Both run under launchd with `KeepAlive`, so they start at login and restart on
crash. To control them:

```sh
launchctl unload ~/Library/LaunchAgents/network.1707.reddit-tunnel.plist   # stop
launchctl load   ~/Library/LaunchAgents/network.1707.reddit-tunnel.plist   # start
launchctl list | grep reddit                                              # status (0 = healthy)
```

> **This machine hosts other tunnels** (budget, salonkit-dev, listen-dev…) that
> share `~/.cloudflared/cert.pem`. `~/.cloudflared/config.yml` belongs to
> *budget* — a bare `cloudflared tunnel run` would start the wrong tunnel.
> Always pass `--config ~/.cloudflared/reddit-reader.yml`, as the plist does.

## Notes

- `bootstrap/app.php` trusts all proxies. Required behind the tunnel: without
  it Laravel sees plain HTTP, generates `http://` URLs, and WebAuthn rejects the
  origin mismatch.
- Passkeys are bound to the relying-party ID, which is derived from `APP_URL`
  (now `reddit.1707.network`). Change the hostname and existing passkeys stop
  working — they must be re-enrolled.
- Reddit responses are cached (listings 90s, comment pages 300s, sub search 1h).
