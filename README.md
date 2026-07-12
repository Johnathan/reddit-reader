# Reddit Reader

Personal single-user Reddit viewer. Laravel 13 + SQLite, server-rendered Blade
(Tailwind via CDN, no build step), passkey-only auth. Wraps Reddit's JSON
endpoints — no posting, read only.

## First run

```sh
php artisan serve
```

1. **Reddit credentials — required, do this first.** Reddit blocks *anonymous*
   access to the JSON endpoints (this is not an IP block and cannot be spoofed
   past: browser user-agents, full Chrome header sets and TLS-fingerprint
   impersonation were all tried and all get 403. Your phone works only because
   it's logged in). OAuth is the supported path. Create an app at
   <https://www.reddit.com/prefs/apps> → "create another app" → type
   **script**, name anything, redirect uri `http://localhost:8000`. Paste the
   id (string under the app name) and secret into `.env`:

   ```
   REDDIT_CLIENT_ID=...
   REDDIT_CLIENT_SECRET=...
   ```

   With credentials set, requests go to `oauth.reddit.com` with an app-only
   token (auto-minted and cached ~50 min), which is not blocked. Verify with:

   ```sh
   php artisan reddit:check
   ```

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

## Notes

- Passkeys are bound to the hostname (`localhost` via `APP_URL`). To use it
  from your phone you'll need an https origin (e.g. Tailscale Serve or a
  Cloudflare tunnel) — set `APP_URL` accordingly and re-enroll passkeys; see
  comments in `config/passkeys.php`.
- Reddit responses are cached (listings 90s, comment pages 300s, sub search 1h).
