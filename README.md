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

## 18+ / NSFW subreddits

Age-gated subs are **invisible to anonymous clients** — and not with an honest
error. `r/gonewild` (5.5M subscribers, `over18: true`) returns HTTP 200 with an
**empty listing**, and NSFW subs are stripped from search results entirely. The
old `over18=1` cookie trick no longer works.

The only thing that lifts the gate is a **logged-in Reddit account**. To enable:

1. Create an app at <https://www.reddit.com/prefs/apps> → "create another app"
   → type **web app** (not "script" — we use the authorization-code flow).
   Redirect URI, exactly:

   ```
   https://reddit.1707.network/reddit/callback
   ```

2. Put the id (the string under the app name) and secret in `.env`:

   ```
   REDDIT_CLIENT_ID=...
   REDDIT_CLIENT_SECRET=...
   ```

3. Go to `/subs` and hit **Connect Reddit**. You approve on Reddit's own site —
   your Reddit password never touches this app. We keep only the refresh token,
   encrypted at rest.

4. Make sure 18+ content is enabled in your Reddit account preferences
   (<https://www.reddit.com/settings/preferences> → "Show mature content").
   Reddit honours the *account's* setting; the app can't override it.

Once linked, every request uses your user token against `oauth.reddit.com` and
the JS-challenge path is bypassed entirely (it's only needed for anonymous
browsing). Disconnect any time from `/subs`.

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
