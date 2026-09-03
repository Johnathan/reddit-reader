<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Turbo turns every link + form into an in-place fetch — no full page
         reloads — and the view-transition tag animates the swaps natively. --}}
    <meta name="view-transition" content="same-origin">
    <title>@yield('title', 'reader')</title>

    {{-- Installable PWA: manifest, icons, and iOS home-screen meta --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#efe7d6">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png?v=3">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Reddit Reader">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Warm "paper" theme — remap Tailwind's neutral/orange palettes so the
        // dark-authored utility classes render as a light cream/navy/amber scheme.
        const paper = {
            100: '#1e2740', 200: '#293251', 300: '#46506b', 400: '#6a6250',
            500: '#7c705a', 600: '#9a8c70', 700: '#cbbb98', 800: '#e5dcc7',
            900: '#fbf8f1', 950: '#efe7d6',
        };
        tailwind.config = {
            theme: { extend: { colors: {
                neutral: paper,
                zinc: paper,
                orange: { 300: '#b8843a', 400: '#a8722a', 500: '#93611c', 600: '#7d5115' },
                emerald: { 200: '#065f46', 300: '#047857', 400: '#059669', 700: '#34d399', 900: '#d1fae5', 950: '#ecfdf5' },
                green:   { 200: '#14532d', 700: '#4ade80', 900: '#dcfce7' },
                red:     { 200: '#7f1d1d', 300: '#991b1b', 400: '#b91c1c', 700: '#f87171', 900: '#fee2e2', 950: '#fef2f2' },
            } } },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.23/dist/turbo.es2017-umd.min.js"></script>
    <style>
        :root { --topbar-h: 3rem; --tabbar-h: 3.5rem; --sidebar-w: 15rem; --shell-gap: 1.5rem; --shell-max: 42rem; }
        html { color-scheme: light; -webkit-text-size-adjust: 100%; }
        body { -webkit-tap-highlight-color: transparent; }

        /* Mobile: a single centred column, exactly as before (this pair of
           rules replaces the max-w-2xl/mx-auto that used to sit on <main>). */
        .shell, .topbar-inner { max-width: var(--shell-max); margin-inline: auto; }

        /* Desktop: put the wasted horizontal space to work as a nav rail. The
           tab bar hides, so its height reserves nothing at the foot of the
           page. Nothing in here applies below 1024px. */
        @media (min-width: 1024px) {
            :root { --tabbar-h: 0rem; --shell-max: 60rem; }

            .shell { display: flex; gap: var(--shell-gap); }
            .shell > main { flex: 1 1 0; min-width: 0; max-width: 42rem; }

            /* The aside stretches to the row height, so the nav inside it can
               stick under the fixed top bar while the feed scrolls past. */
            .sidebar-sticky {
                position: sticky;
                top: calc(var(--topbar-h) + env(safe-area-inset-top) + 0.5rem);
                margin-top: calc(var(--topbar-h) + env(safe-area-inset-top) + 0.5rem);
                max-height: calc(100dvh - var(--topbar-h) - 1.5rem);
            }

            /* Line the top bar up with the columns beneath it: the rail spans
               the sidebar plus the gutter, so the title starts exactly where
               the feed's text does. flex-1 + min-width:0 lets a long feed name
               truncate instead of shoving the search box off the edge. */
            /* Scoped by id: the Tailwind CDN appends its sheet after this one,
               so a bare .topbar-title would lose to its .absolute utility. */
            #app-topbar .topbar-rail { width: calc(var(--sidebar-w) + var(--shell-gap)); flex: none; }
            #app-topbar .topbar-title {
                position: static; transform: none; max-width: none;
                text-align: left; flex: 1 1 auto; min-width: 0;
            }
        }

        /* Keep content from overflowing sideways WITHOUT clamping the page's
           scroll container (overflow-x:hidden on html/body breaks scrolling on
           iOS). overflow-x:clip clips horizontally but never creates a scroll
           container, so vertical scrolling is untouched. */
        main { overflow-x: clip; }
        .reddit-html { overflow-wrap: anywhere; word-break: break-word; }
        .reddit-html a { overflow-wrap: anywhere; word-break: break-all; }
        .reddit-html pre { white-space: pre-wrap; word-break: break-word; }

        /* iOS Safari zooms the page when focusing a form control with
           font-size < 16px; pin form controls to 16px on small screens. */
        @media (max-width: 767px) {
            input:not([type="checkbox"]):not([type="radio"]),
            textarea,
            select { font-size: 16px !important; }
        }

        .turbo-progress-bar { height: 3px; background: #93611c; }

        /* Native push/pop: the content slides directionally, the fixed top and
           tab bars are lifted into their own view-transition groups so they
           stay put (content slides UNDER them). */
        header#app-topbar { view-transition-name: topbar; }
        nav#app-tabbar { view-transition-name: tabbar; }
        @media (prefers-reduced-motion: no-preference) {
            ::view-transition-group(root) { animation-duration: .32s; animation-timing-function: cubic-bezier(.32,.72,0,1); }
            ::view-transition-group(topbar), ::view-transition-group(tabbar) { animation-duration: 0s; }
            @keyframes vt-in-right { from { transform: translateX(100%); } }
            @keyframes vt-out-left { to { transform: translateX(-22%); opacity: .5; } }
            @keyframes vt-in-left { from { transform: translateX(-22%); opacity: .5; } }
            @keyframes vt-out-right { to { transform: translateX(100%); } }
            html[data-turbo-visit-direction="forward"]::view-transition-old(root) { animation-name: vt-out-left; }
            html[data-turbo-visit-direction="forward"]::view-transition-new(root) { animation-name: vt-in-right; }
            html[data-turbo-visit-direction="back"]::view-transition-old(root) { animation-name: vt-out-right; }
            html[data-turbo-visit-direction="back"]::view-transition-new(root) { animation-name: vt-in-left; }
        }

        /* A horizontal push/pop is a phone idiom — it reads as a glitch beside
           a static sidebar. Desktop cross-fades instead. These selectors match
           the specificity of the directional rules above and come later, so
           they win. The sidebar is lifted out of the root group so it holds
           still like the top bar does. */
        #app-sidebar { view-transition-name: sidebar; }
        @media (min-width: 1024px) and (prefers-reduced-motion: no-preference) {
            @keyframes vt-fade-in { from { opacity: 0; } }
            @keyframes vt-fade-out { to { opacity: 0; } }
            ::view-transition-group(root) { animation-duration: .18s; }
            ::view-transition-group(sidebar) { animation-duration: 0s; }
            html[data-turbo-visit-direction="forward"]::view-transition-old(root),
            html[data-turbo-visit-direction="back"]::view-transition-old(root) { animation-name: vt-fade-out; }
            html[data-turbo-visit-direction="forward"]::view-transition-new(root),
            html[data-turbo-visit-direction="back"]::view-transition-new(root) { animation-name: vt-fade-in; }
        }

        .reddit-html a { color: #93611c; text-decoration: underline; text-underline-offset: 2px; }
        .reddit-html p { margin: 0.5em 0; }
        .reddit-html blockquote { border-left: 3px solid #cbbb98; padding-left: 0.75em; margin: 0.5em 0; color: #6a6250; }
        .reddit-html pre { background: #f3ecda; padding: 0.5em; border-radius: 0.5em; overflow-x: auto; }
        .reddit-html code { background: #ece3cf; padding: 0.1em 0.3em; border-radius: 0.25em; font-size: 0.9em; }
        .reddit-html pre code { background: none; padding: 0; }
        .reddit-html ul, .reddit-html ol { margin: 0.5em 0; padding-left: 1.5em; }
        .reddit-html ul { list-style-type: disc; }
        .reddit-html ol { list-style-type: decimal; }
        .reddit-html table { border-collapse: collapse; display: block; overflow-x: auto; }
        .reddit-html th, .reddit-html td { border: 1px solid #d9ccb2; padding: 0.3em 0.6em; }
        .nsfw-img { transition: filter 0.15s ease; cursor: pointer; }

        /* Posts you've already opened: the title quiets down, visited-link
           style, so the unread ones stand out when scanning the feed. */
        [data-mark-read].post-read { color: #8a7f68; font-weight: 400; }
        [data-mark-read].post-read:hover { color: #b8843a; }

        /* Small icon buttons get a finger-sized hit area without changing the
           layout: the pseudo-element overflows the button invisibly. Apple asks
           for 44pt, Material for 48dp, WCAG 2.2 AA for 24px — a 16px icon plus
           14px of overflow each way clears all three at 44px. */
        .tap-target { position: relative; }
        .tap-target::after { content: ''; position: absolute; inset: -14px -12px; }

        /* Swipe-to-save. touch-action: pan-y is what makes this feel native —
           it tells the compositor that vertical scrolling is still its job and
           only the horizontal axis is ours, so the feed never stutters waiting
           on JS. Without it we'd have to preventDefault in a non-passive
           touchmove and fight the scroller. */
        [data-swipe] { touch-action: pan-y; will-change: transform; background-color: #efe7d6; }
        [data-swipe-wrap] { position: relative; overflow: hidden; }
        [data-swipe-bg] {
            position: absolute; inset: 0; display: flex; align-items: center;
            padding-left: 1.25rem; pointer-events: none; opacity: 0;
        }
        @media (prefers-reduced-motion: no-preference) {
            [data-swipe].swipe-settling { transition: transform .28s cubic-bezier(.32,.72,0,1); }
        }

        /* oEmbed markup arrives with the provider's own fixed width/height. */
        .expando-embed iframe { width: 100%; height: auto; aspect-ratio: 16 / 9; border: 0; border-radius: 0.5rem; display: block; }

        /* Reading-list reader: a paged deck. The deck fills the space between
           the top and tab bars so only the cards scroll, never the page; each
           card scrolls vertically to read, the deck scrolls horizontally to
           swipe between posts. CSS scroll-snap does the swipe natively. */
        .reader-deck {
            height: calc(100dvh - var(--topbar-h) - var(--tabbar-h)
                         - env(safe-area-inset-top) - env(safe-area-inset-bottom) - 3.5rem);
            scroll-behavior: smooth;
            scrollbar-width: none;
        }
        .reader-deck::-webkit-scrollbar { width: 0; height: 0; }
        .reader-card { scroll-snap-stop: always; }
    </style>
</head>
<body class="bg-neutral-950 text-neutral-100 antialiased"
      @if (session('status') || session('error')) data-flash="{{ session('error') ?: session('status') }}" data-flash-error="{{ session('error') ? '1' : '0' }}" @endif>
    @include('partials.top-bar')

    <div class="shell">
        @include('partials.sidebar')

        <main style="padding-left: max(0.75rem, env(safe-area-inset-left));
                     padding-right: max(0.75rem, env(safe-area-inset-right));
                     padding-top: calc(var(--topbar-h) + env(safe-area-inset-top) + 0.5rem);
                     padding-bottom: calc(var(--tabbar-h) + env(safe-area-inset-bottom) + 1rem);
                     min-height: 100dvh;">
            @yield('content')
        </main>
    </div>

    @include('partials.reading-dock')

    @include('partials.tab-bar')

    <script>
    // One boot for the app's whole lifetime. Turbo re-runs body scripts on each
    // visit, so guard registration; per-page work runs on every turbo:load.
    (function () {
        if (window.__readerBooted) return;
        window.__readerBooted = true;

        const SUGGEST_URL = @json(route('subs.suggest'));
        const fmt = n => n >= 1e6 ? (n / 1e6).toFixed(1) + 'm' : n >= 1e3 ? (n / 1e3).toFixed(1) + 'k' : '' + n;
        const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

        // ---- floating toast (fixed position, never reflows) -----------------
        window.showToast = function (message, isError) {
            var t = document.createElement('div');
            t.setAttribute('role', 'status');
            t.dataset.toast = '1';
            t.className = 'fixed left-1/2 -translate-x-1/2 z-[60] flex items-center gap-2 max-w-[calc(100%-1.5rem)] px-4 py-2.5 rounded-full text-sm font-medium bg-neutral-900 text-neutral-100 border border-neutral-700 shadow-lg';
            t.style.bottom = 'calc(1rem + env(safe-area-inset-bottom))';
            t.style.transition = 'opacity .3s ease, transform .3s ease';
            var dot = document.createElement('span');
            dot.className = 'w-2 h-2 rounded-full shrink-0 ' + (isError ? 'bg-red-500' : 'bg-emerald-500');
            var txt = document.createElement('span');
            txt.className = 'truncate';
            txt.textContent = message;
            t.appendChild(dot); t.appendChild(txt);
            document.body.appendChild(t);
            setTimeout(function () {
                t.style.opacity = '0';
                t.style.transform = 'translate(-50%, 10px)';
                setTimeout(function () { t.remove(); }, 320);
            }, 2600);
        };

        // ---- custom confirm dialog (replaces the ugly native confirm()) -----
        window.confirmDialog = function (message, opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                const overlay = document.createElement('div');
                overlay.dataset.modal = '1';
                overlay.className = 'fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/40 opacity-0 transition-opacity duration-150';
                const card = document.createElement('div');
                card.setAttribute('role', 'dialog');
                card.setAttribute('aria-modal', 'true');
                card.className = 'w-full sm:max-w-sm m-3 rounded-2xl bg-neutral-900 border border-neutral-700 shadow-2xl p-4 translate-y-3 sm:translate-y-0 transition-transform duration-150';
                card.style.marginBottom = 'calc(0.75rem + env(safe-area-inset-bottom))';

                const msg = document.createElement('p');
                msg.className = 'text-neutral-100 text-sm mb-4';
                msg.textContent = message;

                const rowEl = document.createElement('div');
                rowEl.className = 'flex gap-2 justify-end';
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'px-4 py-2 rounded-full text-sm font-medium bg-neutral-800 text-neutral-300 hover:bg-neutral-700';
                cancel.textContent = opts.cancelLabel || 'Cancel';
                const ok = document.createElement('button');
                ok.type = 'button';
                ok.className = 'px-4 py-2 rounded-full text-sm font-medium text-white ' + (opts.danger ? 'bg-red-600 hover:bg-red-500' : 'bg-orange-500 hover:bg-orange-400');
                ok.textContent = opts.okLabel || 'Confirm';

                rowEl.appendChild(cancel); rowEl.appendChild(ok);
                card.appendChild(msg); card.appendChild(rowEl);
                overlay.appendChild(card);
                document.body.appendChild(overlay);
                requestAnimationFrame(function () { overlay.classList.remove('opacity-0'); card.classList.remove('translate-y-3'); });

                function done(val) {
                    document.removeEventListener('keydown', onKey);
                    overlay.classList.add('opacity-0');
                    card.classList.add('translate-y-3');
                    setTimeout(function () { overlay.remove(); }, 150);
                    resolve(val);
                }
                function onKey(e) { if (e.key === 'Escape') done(false); else if (e.key === 'Enter') done(true); }
                cancel.addEventListener('click', function () { done(false); });
                ok.addEventListener('click', function () { done(true); });
                overlay.addEventListener('click', function (e) { if (e.target === overlay) done(false); });
                document.addEventListener('keydown', onKey);
                ok.focus();
            });
        };

        // ---- collapse/expand a comment (its OWN subtree, not an ancestor's) --
        // Triggers live in two places: the header row and the vertical thread
        // line. Both sit inside the same [data-comment-wrap], so resolve the
        // target from there — the thread line is a SIBLING of [data-comment],
        // so closest('[data-comment]') from it would grab the parent comment.
        window.toggleComment = function (el) {
            const wrap = el.closest('[data-comment-wrap]');
            const c = wrap && wrap.querySelector(':scope > [data-comment]');
            if (!c) return;
            const body = c.querySelector(':scope > .comment-body-wrap > .comment-collapsible');
            if (!body) return;
            const collapsed = body.classList.toggle('hidden');
            const label = c.querySelector(':scope > .comment-head > [data-collapse-btn]');
            if (label) label.textContent = collapsed ? '[+]' : '[-]';
        };

        // The [-] button lives inside the clickable header, so it needs no
        // handler of its own — its click bubbles up to exactly one trigger.
        document.addEventListener('click', function (e) {
            const t = e.target.closest('[data-collapse]');
            if (!t || e.target.closest('a')) return;
            e.preventDefault();
            toggleComment(t);
        });

        function showFlash() {
            var b = document.body;
            if (b && b.dataset.flash) {
                showToast(b.dataset.flash, b.dataset.flashError === '1');
                delete b.dataset.flash;
                delete b.dataset.flashError;
            }
        }

        // ---- subscribe / unsubscribe in place (delegated, bound once) -------
        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-sub-toggle]');
            if (!btn || btn.disabled) return;
            e.preventDefault();
            const sub = btn.dataset.subreddit;
            const wasSubscribed = btn.dataset.subscribed === '1';
            const peers = document.querySelectorAll('[data-sub-toggle][data-subreddit="' + CSS.escape(sub) + '"]');
            peers.forEach(b => b.disabled = true);
            try {
                let res;
                if (wasSubscribed) {
                    res = await fetch('/subs/' + encodeURIComponent(sub), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
                } else {
                    res = await fetch('/subs', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ subreddit: sub }) });
                }
                if (!res.ok) throw new Error('failed');
                const data = await res.json();
                const now = !!data.subscribed;
                peers.forEach(function (b) {
                    b.dataset.subscribed = now ? '1' : '0';
                    b.textContent = now ? b.dataset.labelUnsub : b.dataset.labelSub;
                    b.className = b.dataset.classBase + ' ' + (now ? b.dataset.classUnsub : b.dataset.classSub);
                });
                showToast(data.message || (now ? 'Subscribed' : 'Unsubscribed'));
            } catch (err) {
                showToast('Something went wrong. Please try again.', true);
            } finally {
                peers.forEach(b => b.disabled = false);
            }
        });

        // ---- generic in-place form (add-to-feed, feed create/add/remove) ----
        // Any <form data-ajax data-turbo="false"> submits via fetch and never
        // navigates. Optional behaviours via attributes:
        //   data-ajax-remove          remove the closest [data-removable] on success
        //   data-ajax-append="<sel>"  insert the response html before <sel>
        document.addEventListener('submit', async function (e) {
            const form = e.target.closest('form[data-ajax]');
            if (!form) return;
            e.preventDefault();
            if (form.dataset.confirm) {
                const ok = await confirmDialog(form.dataset.confirm, { danger: true, okLabel: form.dataset.confirmOk || 'Delete' });
                if (!ok) return;
            }
            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch(form.action, {
                    method: 'POST', // Laravel reads _method for DELETE/PUT from the body.
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: new FormData(form),
                });
                if (!res.ok) throw new Error('failed');
                const data = await res.json().catch(() => ({}));

                if (form.hasAttribute('data-ajax-remove')) {
                    const el = form.closest('[data-removable]');
                    if (el) { el.style.transition = 'opacity .2s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 200); }
                }
                if (form.dataset.ajaxAppend && data.html) {
                    const anchor = document.querySelector(form.dataset.ajaxAppend);
                    if (anchor) {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = data.html.trim();
                        const node = tmp.firstElementChild;
                        if (node) anchor.parentNode.insertBefore(node, anchor);
                    }
                }
                form.reset();
                const menu = form.closest('details[open]');
                if (menu) menu.open = false;
                if (data.message) showToast(data.message);
            } catch (err) {
                showToast('Something went wrong. Please try again.', true);
            } finally {
                if (btn) btn.disabled = false;
            }
        });

        // ---- reading list: add from the feed, peek at the sticky pad --------
        // Saving is optimistic: the icon flips immediately and rolls back only
        // if the request fails, so a tap never interrupts a scroll.
        function readingApi(url, method, body) {
            return fetch(url, {
                method: method,
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: body,
            }).then(function (r) {
                if (!r.ok) throw new Error('failed');
                return r.json();
            });
        }

        function paintLaterButton(btn, saved) {
            btn.dataset.inlist = saved ? '1' : '0';
            btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
            btn.setAttribute('aria-label', saved ? 'Remove from reading list' : 'Add to reading list');
            btn.classList.toggle('text-orange-500', saved);
            btn.classList.toggle('hover:text-orange-500', !saved);
            const icon = btn.querySelector('[data-later-icon]');
            if (icon) icon.setAttribute('fill', saved ? 'currentColor' : 'none');
            const label = btn.querySelector('[data-later-label]');
            if (label) label.textContent = saved ? 'In list' : 'Read later';
        }

        // Every card for the same post moves together (feed + post page).
        function paintAllLaterButtons(postId, saved) {
            document.querySelectorAll('[data-later][data-post-id="' + CSS.escape(postId) + '"]')
                .forEach(function (b) { paintLaterButton(b, saved); });
        }

        function updateDock(state) {
            const dock = document.getElementById('reading-dock');
            if (!dock) return;
            dock.dataset.count = state.count;
            dock.classList.toggle('hidden', state.count === 0);
            const count = dock.querySelector('[data-dock-count]');
            if (count) count.textContent = state.count;
            const unread = dock.querySelector('[data-dock-unread]');
            if (unread) {
                unread.textContent = state.unread;
                unread.classList.toggle('hidden', !state.unread);
            }
            const empty = dock.querySelector('[data-dock-empty]');
            if (empty) empty.classList.toggle('hidden', state.count !== 0);
        }

        function addDockRow(item) {
            const list = document.querySelector('[data-dock-items]');
            if (!list || list.querySelector('[data-dock-row][data-post-id="' + CSS.escape(item.post_id) + '"]')) return;
            const row = document.createElement('div');
            row.dataset.dockRow = '';
            row.dataset.postId = item.post_id;
            row.dataset.source = item.source || 'reddit';
            row.className = 'flex items-start gap-2 px-3 py-2 hover:bg-neutral-800/60';
            const link = document.createElement('a');
            link.href = item.url;
            link.className = 'min-w-0 flex-1 group';
            const title = document.createElement('span');
            title.className = 'block text-xs leading-snug text-neutral-200 group-hover:text-orange-300 line-clamp-2';
            title.textContent = item.title;
            const meta = document.createElement('span');
            meta.className = 'block text-[11px] text-neutral-500 mt-0.5';
            meta.textContent = item.label || ('r/' + item.subreddit);
            link.appendChild(title); link.appendChild(meta);
            const del = document.createElement('button');
            del.type = 'button';
            del.dataset.dockRemove = '';
            del.dataset.postId = item.post_id;
            del.dataset.source = item.source || 'reddit';
            del.setAttribute('aria-label', 'Remove from reading list');
            del.className = 'shrink-0 p-1 -mr-1 text-neutral-600 hover:text-red-400';
            del.innerHTML = '<svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
            row.appendChild(link); row.appendChild(del);
            list.prepend(row);
        }

        function dropRows(postId) {
            document.querySelectorAll('[data-dock-row][data-post-id="' + CSS.escape(postId) + '"],' +
                '[data-reading-row][data-post-id="' + CSS.escape(postId) + '"]')
                .forEach(function (el) {
                    el.style.transition = 'opacity .2s';
                    el.style.opacity = '0';
                    setTimeout(function () { el.remove(); }, 200);
                });
        }

        document.addEventListener('click', async function (e) {
            // -- save / unsave -------------------------------------------------
            const save = e.target.closest('[data-later]');
            if (save) {
                e.preventDefault();
                if (save.__busy) return;
                save.__busy = true;
                const d = save.dataset;
                // The state attribute is data-inlist (see paintLaterButton);
                // reading d.saved here once made un-saving impossible.
                const wasSaved = d.inlist === '1';
                paintAllLaterButtons(d.postId, !wasSaved);   // optimistic
                try {
                    let state;
                    if (wasSaved) {
                        state = await readingApi('/reading-list/' + encodeURIComponent(d.postId), 'POST',
                            new URLSearchParams({ _method: 'DELETE', source: d.source || 'reddit' }));
                        dropRows(d.postId);
                    } else {
                        state = await readingApi(@json(route('reading.store')), 'POST', new URLSearchParams({
                            post_id: d.postId, subreddit: d.subreddit, title: d.title, slug: d.slug || '',
                            thumbnail: d.thumbnail || '', score: d.score || 0, num_comments: d.comments || 0,
                            source: d.source || 'reddit', url: d.url || '',
                        }));
                        if (state.item) addDockRow(state.item);
                    }
                    updateDock(state);
                } catch (err) {
                    paintAllLaterButtons(d.postId, wasSaved);   // roll back
                    showToast('Could not update your reading list.', true);
                } finally {
                    save.__busy = false;
                }
                return;
            }

            // -- flip the pad open / shut --------------------------------------
            const toggle = e.target.closest('[data-dock-toggle]');
            if (toggle) {
                e.preventDefault();
                const panel = document.querySelector('[data-dock-panel]');
                if (panel) {
                    panel.hidden = !panel.hidden;
                    toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                }
                return;
            }

            // -- remove (from the pad or the full list) ------------------------
            const remove = e.target.closest('[data-dock-remove],[data-reading-remove]');
            if (remove) {
                e.preventDefault();
                const id = remove.dataset.postId;
                dropRows(id);
                paintAllLaterButtons(id, false);
                try {
                    updateDock(await readingApi('/reading-list/' + encodeURIComponent(id), 'POST',
                        new URLSearchParams({ _method: 'DELETE', source: remove.dataset.source || 'reddit' })));
                } catch (err) {
                    showToast('Could not remove that.', true);
                }
                return;
            }

            // -- mark read / unread --------------------------------------------
            const read = e.target.closest('[data-reading-read]');
            if (read) {
                e.preventDefault();
                try {
                    const state = await readingApi('/reading-list/' + encodeURIComponent(read.dataset.postId) + '/read', 'POST',
                        new URLSearchParams({ source: read.dataset.source || 'reddit' }));
                    read.setAttribute('aria-pressed', state.read ? 'true' : 'false');
                    read.classList.toggle('text-emerald-400', state.read);
                    read.classList.toggle('hover:text-emerald-400', !state.read);
                    const lbl = read.querySelector('[data-reading-read-label]');
                    if (lbl) lbl.textContent = state.read ? 'Read' : 'Mark read';
                    const row = read.closest('[data-reading-row]');
                    if (row) row.classList.toggle('opacity-50', state.read);
                    const n = document.querySelector('[data-list-unread]');
                    if (n) n.textContent = state.unread;
                    updateDock(state);
                } catch (err) {
                    showToast('Could not update that.', true);
                }
                return;
            }

            // -- clear the list ------------------------------------------------
            const clear = e.target.closest('[data-list-clear]');
            if (clear) {
                e.preventDefault();
                const readOnly = clear.hasAttribute('data-read-only');
                const ok = await confirmDialog(
                    readOnly ? 'Clear everything you have already read?' : 'Clear the whole reading list?',
                    { danger: !readOnly, okLabel: 'Clear' },
                );
                if (!ok) return;
                try {
                    const state = await readingApi(@json(route('reading.clear')), 'POST',
                        new URLSearchParams({ _method: 'DELETE', read_only: readOnly ? '1' : '0' }));
                    showToast(state.message);
                    Turbo ? Turbo.visit(location.href, { action: 'replace' }) : location.reload();
                } catch (err) {
                    showToast('Could not clear the list.', true);
                }
            }
        });

        // ---- archive: keep a copy of a post or comment ----------------------
        // Not optimistic like "read later": the server has to fetch and archive
        // the content, so the button shows it is working and only flips once
        // the copy actually exists.
        function paintArchiveButton(btn, on) {
            btn.dataset.archived = on ? '1' : '0';
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.setAttribute('aria-label', on ? 'Remove from archive' : 'Save to archive');
            btn.classList.toggle('text-emerald-400', on);
            btn.classList.toggle('hover:text-emerald-400', !on);
            const icon = btn.querySelector('[data-archive-icon]');
            if (icon) icon.setAttribute('fill', on ? 'currentColor' : 'none');
            const label = btn.querySelector('[data-archive-label]');
            if (label) label.textContent = on ? 'Saved' : 'Save';
        }

        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-archive]');
            if (!btn || btn.__busy) return;
            e.preventDefault();

            const d = btn.dataset;
            const on = d.archived === '1';
            btn.__busy = true;
            btn.classList.add('opacity-50');

            try {
                let res;
                if (on) {
                    res = await readingApi('/saved/' + encodeURIComponent(d.redditId), 'POST',
                        new URLSearchParams({ _method: 'DELETE', source: d.source || 'reddit' }));
                    const row = btn.closest('[data-saved-row]');
                    if (row) { row.style.transition = 'opacity .2s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 200); }
                } else {
                    res = await readingApi(@json(route('saved.store')), 'POST', new URLSearchParams({
                        kind: d.kind, reddit_id: d.redditId, subreddit: d.subreddit, post_id: d.postId || '',
                        source: d.source || 'reddit', url: d.url || '',
                    }));
                }
                document.querySelectorAll('[data-archive][data-reddit-id="' + CSS.escape(d.redditId) + '"]')
                    .forEach(function (b) { paintArchiveButton(b, !on); });
                showToast(res.message);
            } catch (err) {
                showToast('Could not update your archive.', true);
            } finally {
                btn.__busy = false;
                btn.classList.remove('opacity-50');
            }
        });

        // ---- share a post's public link -------------------------------------
        // Post URLs are viewable logged-out, so handing one out is safe. The
        // native share sheet where there is one (iOS/Android PWA), otherwise
        // straight to the clipboard.
        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-share]');
            if (!btn) return;
            e.preventDefault();
            const url = btn.dataset.shareUrl;
            if (navigator.share) {
                try {
                    await navigator.share({ title: btn.dataset.shareTitle || document.title, url: url });
                } catch (err) { /* user closed the sheet — not an error */ }
                return;
            }
            try {
                await navigator.clipboard.writeText(url);
                showToast('Link copied — anyone can open it');
            } catch (err) {
                showToast('Could not copy the link.', true);
            }
        });

        // Folder / tags / note save on blur — no explicit save button to hunt.
        document.addEventListener('change', async function (e) {
            const field = e.target.closest('[data-organise]');
            if (!field) return;

            // "+ New folder…" opens the inline name input instead of saving.
            if (field.dataset.organise === 'folder' && field.value === '__new__') {
                field.value = field.dataset.prev || '';
                const input = field.parentElement.querySelector('[data-new-folder]');
                if (input) { input.hidden = false; input.focus(); }
                return;
            }
            if (field.dataset.organise === 'folder') field.dataset.prev = field.value;

            const body = new URLSearchParams({ _method: 'PATCH' });
            body.set(field.dataset.organise === 'folder' ? 'folder_id' : field.dataset.organise, field.value);
            try {
                await readingApi('/saved/' + encodeURIComponent(field.dataset.id), 'POST', body);
                showToast('Updated');
            } catch (err) {
                showToast('Could not save that change.', true);
            }
        });

        // Create the folder, file the item in it, and teach every other
        // Organise popover on the page about it — no reload.
        async function createFolderInline(input) {
            const name = input.value.trim();
            if (!name) { input.hidden = true; return; }
            try {
                const folder = await readingApi(@json(route('saved.folders.store')), 'POST',
                    new URLSearchParams({ name: name }));

                document.querySelectorAll('select[data-organise="folder"]').forEach(function (sel) {
                    if ([...sel.options].some(o => o.value === String(folder.id))) return;
                    const newOpt = [...sel.options].find(o => o.value === '__new__');
                    sel.insertBefore(new Option(folder.name, folder.id), newOpt || null);
                });

                const sel = input.parentElement.querySelector('select[data-organise="folder"]');
                if (sel) { sel.value = String(folder.id); sel.dataset.prev = String(folder.id); }

                await readingApi('/saved/' + encodeURIComponent(input.dataset.id), 'POST',
                    new URLSearchParams({ _method: 'PATCH', folder_id: folder.id }));

                showToast('Filed under “' + folder.name + '”');
                input.value = '';
                input.hidden = true;
            } catch (err) {
                showToast('Could not create that folder.', true);
            }
        }

        document.addEventListener('keydown', function (e) {
            const input = e.target.closest('[data-new-folder]');
            if (input && e.key === 'Enter') { e.preventDefault(); createFolderInline(input); }
            if (input && e.key === 'Escape') { input.value = ''; input.hidden = true; }
        });

        document.addEventListener('focusout', function (e) {
            const input = e.target.closest('[data-new-folder]');
            if (input && !input.hidden) createFolderInline(input);
        });

        // ---- swipe a card to the right to save it ---------------------------
        // Tuned from the platform gesture specs rather than guessed:
        //
        //  * EDGE_GUARD — iOS Safari's back/forward swipe lives in the outer
        //    ~20px and is NOT governed by touch-action, so a gesture starting
        //    there is left alone entirely. Fighting it (preventDefault on
        //    touchstart) is possible but steals the user's way back.
        //  * LOCK_SLOP / LOCK_RATIO — decide scroll-vs-swipe from the first few
        //    pixels, then commit. Without direction locking a diagonal drag
        //    fights the scroller and feels broken.
        //  * COMMIT_FRACTION / COMMIT_MIN — Material's swipe-to-dismiss uses a
        //    positional threshold (56dp) OR a velocity fling; matching both
        //    means a slow deliberate drag and a quick flick both work.
        //  * RESIST — past the commit point the card gets heavier, which is the
        //    tactile cue that you have gone far enough.
        const EDGE_GUARD = 24;
        const LOCK_SLOP = 8;
        const LOCK_RATIO = 1.2;
        const COMMIT_FRACTION = 0.25;
        const COMMIT_MIN = 64;
        const FLING_VELOCITY = 0.45;   // px/ms
        const FLING_MIN = 40;
        const VELOCITY_WINDOW = 120;   // ms of trailing movement to average
        const MIN_VELOCITY_SPAN = 16;  // ~1 frame; below this we can't measure
        const MAX_OVERPULL = 44;   // how far past the commit point it can stretch
        const RESIST = 0.35;

        let sw = null;

        function swipeCommitDistance(card) {
            return Math.max(COMMIT_MIN, card.offsetWidth * COMMIT_FRACTION);
        }

        function swipePaint(card, dx) {
            card.style.transform = dx ? 'translate3d(' + dx + 'px,0,0)' : '';
            const bg = card.parentElement.querySelector('[data-swipe-bg]');
            if (bg) {
                const p = Math.min(1, dx / swipeCommitDistance(card));
                bg.style.opacity = p;
                // The icon grows as you approach the commit point, then pops.
                bg.style.transform = 'scale(' + (0.8 + 0.2 * p) + ')';
            }
        }

        function swipeSettle(card, then) {
            card.classList.add('swipe-settling');
            swipePaint(card, 0);
            setTimeout(function () {
                card.classList.remove('swipe-settling');
                card.style.transform = '';
                if (then) then();
            }, 280);
        }

        document.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) { sw = null; return; }
            const t = e.touches[0];

            // Leave the system's back/forward gesture alone.
            if (t.clientX < EDGE_GUARD || t.clientX > window.innerWidth - EDGE_GUARD) { sw = null; return; }

            const card = e.target.closest('[data-swipe]');
            if (!card || e.target.closest('a,button')) { sw = null; return; }

            sw = { card: card, x0: t.clientX, y0: t.clientY, t0: e.timeStamp, dx: 0, raw: 0, axis: null,
                   samples: [{ x: t.clientX, t: e.timeStamp }] };
            card.classList.remove('swipe-settling');
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!sw || e.touches.length !== 1) return;
            const t = e.touches[0];
            const dx = t.clientX - sw.x0;
            const dy = t.clientY - sw.y0;

            // Decide once, then commit to that axis for the whole gesture.
            if (!sw.axis) {
                if (Math.abs(dy) > LOCK_SLOP && Math.abs(dy) > Math.abs(dx)) { sw = null; return; }
                if (Math.abs(dx) > LOCK_SLOP && Math.abs(dx) > Math.abs(dy) * LOCK_RATIO) sw.axis = 'x';
                else return;
            }

            // How far the finger actually travelled decides whether we commit;
            // the rubber-banded value below is only what gets drawn. Conflating
            // the two means a clamped visual can make the threshold
            // unreachable.
            sw.raw = dx;

            // Rightward only; a left drag just rubber-bands against the stop.
            let pull = dx;
            const commit = swipeCommitDistance(sw.card);
            if (pull < 0) pull = pull * RESIST * 0.5;
            else if (pull > commit) pull = commit + (pull - commit) * RESIST;
            sw.dx = Math.max(-24, Math.min(commit + MAX_OVERPULL, pull));

            // Keep a short trailing window so the release velocity reflects how
            // the finger was moving at the END, not the average over a gesture
            // that may have paused halfway.
            sw.samples.push({ x: t.clientX, t: e.timeStamp });
            while (sw.samples.length > 2 && e.timeStamp - sw.samples[0].t > VELOCITY_WINDOW) sw.samples.shift();

            swipePaint(sw.card, sw.dx);
        }, { passive: true });   // touch-action: pan-y does the blocking for us

        document.addEventListener('touchend', function (e) {
            if (!sw) return;
            const card = sw.card;

            if (sw.axis !== 'x') { sw = null; return; }

            // Velocity over the trailing window. If the samples span too little
            // time to measure honestly, call it zero rather than dividing by
            // ~0 and treating a twitch as a fling.
            const first = sw.samples[0];
            const last = sw.samples[sw.samples.length - 1];
            const span = last.t - first.t;
            const velocity = span >= MIN_VELOCITY_SPAN ? (last.x - first.x) / span : 0;

            const past = sw.raw >= swipeCommitDistance(card);
            const flung = velocity >= FLING_VELOCITY && sw.raw >= FLING_MIN;

            if (past || flung) {
                // Fire on release, not after the settle animation — waiting for
                // the card to slide home first makes the action feel laggy.
                // Reuse the button's own handler so the optimistic update,
                // rollback and dock refresh all behave identically.
                const btn = card.querySelector('[data-later]');
                if (btn) btn.click();
            }

            swipeSettle(card);

            sw = null;
        }, { passive: true });

        document.addEventListener('touchcancel', function () {
            if (sw) swipeSettle(sw.card);
            sw = null;
        }, { passive: true });

        // ---- read posts + "new comments since" badges (RES-style) -----------
        // Server-side: seen_items rows record what you've opened and at what
        // comment count, so every device renders the same dimming and "+N
        // new" badges. Feed HTML arrives already marked; the JS here only
        // (a) dims a card instantly when you click through — the destination
        // page records it — and (b) reports expando opens, which never load a
        // page the server could record from.
        function paintCardRead(card) {
            card.querySelectorAll('[data-mark-read]').forEach(function (a) { a.classList.add('post-read'); });
        }

        function reportSeen(key) {
            fetch(@json(route('seen.store')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ key: key }),
                keepalive: true,   // survives an immediate navigation
            }).catch(function () {});
        }

        // Clicking through to a post: dim it right away for this page's
        // lifetime; the post page itself records the visit.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('[data-mark-read]');
            if (!link) return;
            const card = link.closest('[data-seen]');
            if (card) paintCardRead(card);
        });

        // One-time migration of the retired localStorage tracker.
        (function () {
            let old = null;
            try { old = JSON.parse(localStorage.getItem('reader:seen')); } catch (e) {}
            if (!old || !Object.keys(old).length) { try { localStorage.removeItem('reader:seen'); } catch (e) {} return; }
            fetch(@json(route('seen.import')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: old }),
            }).then(function (r) {
                if (r.ok) { try { localStorage.removeItem('reader:seen'); } catch (e) {} }
            }).catch(function () {});
        })();

        // ---- RES-style inline expandos --------------------------------------
        // Media is built on first open, never up front, so a feed of image and
        // video posts costs no bandwidth until you ask to see it.
        function nsfwImage(img, nsfw) {
            if (!nsfw) return img;
            img.classList.add('blur-md', 'nsfw-img');
            img.addEventListener('click', function (e) {
                e.preventDefault();
                img.classList.toggle('blur-md');
            });
            return img;
        }

        // ---- Reddit video with SOUND -----------------------------------------
        // The fallback mp4 is DASH's video track alone (audio ships separately),
        // which is why v.redd.it videos played silent with a dead volume
        // control. The HLS playlist muxes both: Safari/iOS play it natively,
        // everything else gets hls.js — loaded from CDN only when a video that
        // needs it actually appears, and told not to buffer until you hit play.
        const HLS_CDN = 'https://cdn.jsdelivr.net/npm/hls.js@1.7.1/dist/hls.min.js';
        let hlsLib = null;
        function loadHlsLib() {
            return hlsLib ??= new Promise(function (resolve) {
                const s = document.createElement('script');
                s.src = HLS_CDN;
                s.onload = resolve;
                s.onerror = resolve;   // resolve anyway; caller falls back to mp4
                document.head.appendChild(s);
            });
        }

        function setupHlsVideo(el, hlsUrl, fallback) {
            if (!hlsUrl || el.__hlsSetup) return;
            el.__hlsSetup = true;

            // hls.js FIRST, canPlayType only as the no-MSE fallback. Desktop
            // Chrome now answers "maybe" to application/vnd.apple.mpegurl but
            // its built-in HLS stalls on v.redd.it playlists (observed on
            // Chrome 151: loadstart, then nothing, forever) — so trusting
            // canPlayType first broke every video. Only Safari/iOS, where MSE
            // is absent or hls.js unsupported, should take the native path.
            if (!('MediaSource' in window)) {
                if (el.canPlayType('application/vnd.apple.mpegurl')) {
                    el.innerHTML = '';        // drop any <source> so src wins
                    el.src = hlsUrl;
                }
                return;   // no MSE, no native HLS: keep the silent mp4 over nothing
            }

            loadHlsLib().then(function () {
                if (!window.Hls || !Hls.isSupported()) {
                    // CDN blocked or MSE too limited (e.g. iPad Safari):
                    // native HLS is the next best thing.
                    if (el.canPlayType('application/vnd.apple.mpegurl')) {
                        el.innerHTML = '';
                        el.src = hlsUrl;
                    }
                    return;
                }
                const h = new Hls({ autoStartLoad: false });
                h.on(Hls.Events.ERROR, function (_, data) {
                    if (!data.fatal) return;
                    h.destroy();
                    el.innerHTML = '';
                    if (fallback) el.src = fallback;
                });
                el.innerHTML = '';
                el.removeAttribute('src');
                h.loadSource(hlsUrl);
                h.attachMedia(el);
                // Not a byte of media until the user actually hits play. If
                // play was already pressed while the library loaded, honour it.
                if (el.paused) {
                    el.addEventListener('play', function () { h.startLoad(); }, { once: true });
                } else {
                    h.startLoad();
                }
            });
        }

        // Never autoplays: preload="none" means not a byte is fetched until the
        // user actually hits play.
        function buildVideo(video, nsfw) {
            const el = document.createElement('video');
            el.src = video.src;
            el.controls = true;
            el.preload = 'none';
            el.playsInline = true;
            if (video.poster) el.poster = video.poster;
            el.className = 'w-full rounded-lg mb-2 bg-black' + (nsfw ? ' blur-md nsfw-img' : '');
            if (nsfw) el.addEventListener('click', function () { el.classList.toggle('blur-md'); });
            if (video.hls) setupHlsVideo(el, video.hls, video.src);
            return el;
        }

        function buildEmbed(html) {
            const box = document.createElement('div');
            box.className = 'expando-embed mb-2';
            box.innerHTML = html;
            return box;
        }

        // One image: just show it. Several: a manual carousel — only the image
        // you are looking at is ever fetched.
        function buildImages(urls, nsfw) {
            const wrap = document.createElement('div');
            wrap.className = 'relative mb-2';

            const img = nsfwImage(document.createElement('img'), nsfw);
            img.alt = '';
            img.className += ' w-full rounded-lg bg-neutral-900';
            wrap.appendChild(img);

            if (urls.length === 1) {
                img.src = urls[0];
                return wrap;
            }

            let i = 0;
            const counter = document.createElement('span');
            counter.className = 'absolute top-2 right-2 px-2 py-0.5 rounded-full bg-black/60 text-white text-[11px] font-medium pointer-events-none';

            function arrow(side, path) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'absolute top-1/2 -translate-y-1/2 ' + side +
                    '-2 w-8 h-8 grid place-items-center rounded-full bg-black/50 text-white hover:bg-black/70 disabled:opacity-30';
                b.innerHTML = '<svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"/></svg>';
                return b;
            }

            const prev = arrow('left', 'M15 18l-6-6 6-6');
            const next = arrow('right', 'M9 18l6-6-6-6');

            function show(n) {
                i = Math.max(0, Math.min(n, urls.length - 1));
                img.src = urls[i];
                counter.textContent = (i + 1) + ' / ' + urls.length;
                prev.disabled = i === 0;
                next.disabled = i === urls.length - 1;
            }

            prev.addEventListener('click', function (e) { e.preventDefault(); show(i - 1); });
            next.addEventListener('click', function (e) { e.preventDefault(); show(i + 1); });

            wrap.appendChild(prev);
            wrap.appendChild(next);
            wrap.appendChild(counter);
            show(0);
            return wrap;
        }

        function setExpanded(btn, open) {
            const card = btn.closest('article');
            const panel = card && card.querySelector('[data-expando-panel]');
            if (!panel) return;

            if (open && panel.dataset.loaded !== '1') {
                // RSS cards: the expando IS the article, fetched on first open.
                // `loaded` is only set once the fetch lands — flagging it up
                // front made a failed load permanent: re-opening the card
                // would show the error forever instead of retrying.
                if (btn.dataset.fetch && panel.dataset.loading !== '1') {
                    panel.dataset.loading = '1';
                    panel.innerHTML = '<p class="text-sm text-neutral-500 py-3">Loading article&hellip;</p>';
                    fetch(btn.dataset.fetch, { headers: { 'Accept': 'text/html' } })
                        .then(function (r) { if (!r.ok) throw new Error('failed'); return r.text(); })
                        .then(function (html) { panel.innerHTML = html; panel.dataset.loaded = '1'; })
                        .catch(function () {
                            panel.innerHTML = '<p class="text-sm text-red-300 py-3">Could not load the article. Close and reopen to retry.</p>';
                        })
                        .finally(function () { panel.dataset.loading = '0'; });
                } else if (!btn.dataset.fetch) {
                    panel.dataset.loaded = '1';
                    let media = {};
                    try { media = JSON.parse(btn.dataset.media || '{}'); } catch (e) {}
                    const nsfw = btn.dataset.nsfw === '1';

                    if (media.video) panel.appendChild(buildVideo(media.video, nsfw));
                    if (media.embed) panel.appendChild(buildEmbed(media.embed));
                    if (media.images && media.images.length) panel.appendChild(buildImages(media.images, nsfw));
                }
            }

            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('text-orange-500', open);
            const plus = btn.querySelector('[data-expando-plus]');
            if (plus) plus.setAttribute('d', open ? 'M8 12h8' : 'M12 8v8M8 12h8');
        }

        function setExpandAll(open, scope) {
            (scope || document).querySelectorAll('[data-expando]').forEach(function (b) {
                // Skip RSS article cards when opening: each one costs a
                // server-side extraction, so "expand all" on a newsy feed
                // would fire a burst of them. Media expandos are free until
                // played; articles stay tap-to-open. Closing closes them all.
                if (open && b.dataset.fetch) return;
                setExpanded(b, open);
            });
        }

        function expandAllIsOn() {
            const all = document.querySelector('[data-expando-all]');
            return !!all && all.getAttribute('aria-pressed') === 'true';
        }

        document.addEventListener('click', function (e) {
            const all = e.target.closest('[data-expando-all]');
            if (all) {
                e.preventDefault();
                const open = all.getAttribute('aria-pressed') !== 'true';
                setExpandAll(open);
                all.setAttribute('aria-pressed', open ? 'true' : 'false');
                const label = all.querySelector('[data-expando-all-label]');
                if (label) label.textContent = open ? 'Collapse all' : 'Expand all';
                return;
            }

            const btn = e.target.closest('[data-expando]');
            if (!btn) return;
            e.preventDefault();
            const opening = btn.getAttribute('aria-expanded') !== 'true';
            setExpanded(btn, opening);

            // Unfolding a post inline is reading it too — but only a direct
            // tap; "expand all" doesn't take this path, so it never marks the
            // whole page read. The server keeps the comment-count baseline
            // untouched for these: you saw the content, not the thread.
            if (opening) {
                const card = btn.closest('[data-seen]');
                if (card && !card.__seenReported) {
                    card.__seenReported = true;
                    reportSeen(card.dataset.seen);
                    paintCardRead(card);
                }
            }
        });

        // ---- unfold a subreddit row into a peek at its posts (Explore) ------
        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-unfold]');
            if (!btn) return;
            e.preventDefault();

            const card = btn.closest('[data-unfold-card]');
            const panel = card && card.querySelector('[data-unfold-panel]');
            if (!panel) return;

            const open = panel.hidden;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            const chevron = btn.querySelector('svg');
            if (chevron) chevron.classList.toggle('rotate-180', open);

            if (!open || panel.dataset.loaded === '1') return;

            panel.innerHTML = '<p class="text-xs text-neutral-500 py-1">Loading&hellip;</p>';
            try {
                const res = await fetch(btn.dataset.url, { headers: { 'Accept': 'text/html' } });
                if (!res.ok) throw new Error('failed');
                panel.innerHTML = await res.text();
                panel.dataset.loaded = '1';
            } catch (err) {
                panel.innerHTML = '<p class="text-xs text-red-300 py-1">Could not load posts.</p>';
            }
        });

        // ---- append-based "load more" + infinite scroll --------------------
        let lmObserver = null;
        async function loadMore(trigger) {
            if (!trigger || trigger.__loading) return;
            const url = trigger.getAttribute('href') || trigger.dataset.url;
            if (!url) return;
            trigger.__loading = true;
            const label = trigger.textContent;
            trigger.textContent = 'Loading…';
            try {
                const res = await fetch(url, { headers: { 'Accept': 'text/html' } });
                if (!res.ok) throw new Error('failed');
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const incoming = doc.getElementById('post-list');
                const list = document.getElementById('post-list');
                if (incoming && list) { while (incoming.firstElementChild) list.appendChild(incoming.firstElementChild); }
                // Keep "expand all" honest across appended pages.
                if (expandAllIsOn()) setExpandAll(true, list);
                const newLM = doc.getElementById('load-more');
                const curLM = document.getElementById('load-more');
                if (curLM) {
                    if (newLM) { curLM.replaceWith(newLM); observeLoadMore(); }
                    else { curLM.remove(); }
                }
            } catch (e) {
                trigger.textContent = label;
                trigger.__loading = false;
                showToast('Could not load more.', true);
            }
        }
        function observeLoadMore() {
            const lm = document.getElementById('load-more');
            if (!lm) return;
            if (!lmObserver) {
                lmObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) {
                        if (en.isIntersecting) {
                            const a = en.target.querySelector('[data-load-more]');
                            if (a) loadMore(a);
                        }
                    });
                }, { rootMargin: '600px' });
            }
            lmObserver.observe(lm);
        }
        document.addEventListener('click', function (e) {
            const t = e.target.closest('[data-load-more]');
            if (t) { e.preventDefault(); loadMore(t); }
        });

        // ---- subreddit typeahead (dropdown appended to <body>) --------------
        function initAutocomplete(input) {
            if (input.__ac) return;
            input.__ac = true;
            const mode = input.getAttribute('data-sub-autocomplete') || 'fill';
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('spellcheck', 'false');

            const box = document.createElement('div');
            box.dataset.acBox = '1';
            box.className = 'fixed z-50 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl overflow-hidden hidden';
            document.body.appendChild(box);

            let items = [], active = -1, seq = 0, timer = null;
            function place() { const r = input.getBoundingClientRect(); box.style.left = r.left + 'px'; box.style.top = (r.bottom + 4) + 'px'; box.style.width = r.width + 'px'; }
            function hide() { box.classList.add('hidden'); box.innerHTML = ''; items = []; active = -1; }
            function choose(name) {
                if (mode === 'navigate') { const url = '/r/' + name.toLowerCase(); window.Turbo ? Turbo.visit(url) : location.assign(url); }
                else { input.value = name; hide(); input.focus(); }
            }
            function render() {
                place();
                box.innerHTML = '';
                items.forEach(function (s, i) {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-neutral-800 ' + (i === active ? 'bg-neutral-800' : '');
                    const icon = s.icon ? '<img src="' + s.icon + '" class="w-5 h-5 rounded-full shrink-0" alt="">' : '<span class="w-5 h-5 rounded-full bg-neutral-700 shrink-0"></span>';
                    row.innerHTML = icon + '<span class="text-sm text-neutral-100 truncate">r/' + s.name + '</span>' + (s.subscribers ? '<span class="ml-auto text-xs text-neutral-500 shrink-0">' + fmt(s.subscribers) + '</span>' : '');
                    row.addEventListener('mousedown', ev => { ev.preventDefault(); choose(s.name); });
                    box.appendChild(row);
                });
                box.classList.toggle('hidden', items.length === 0);
            }
            async function query(q) {
                const mine = ++seq;
                try {
                    const res = await fetch(SUGGEST_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (mine !== seq) return;
                    items = Array.isArray(data) ? data : [];
                    active = -1;
                    render();
                } catch (e) {}
            }
            input.addEventListener('input', function () {
                const q = input.value.trim();
                clearTimeout(timer);
                if (q.length < 2) { hide(); return; }
                timer = setTimeout(() => query(q), 150);
            });
            input.addEventListener('keydown', function (e) {
                if (box.classList.contains('hidden')) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); render(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
                else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(items[active].name); }
                else if (e.key === 'Escape') { hide(); }
            });
            input.addEventListener('blur', () => setTimeout(hide, 150));
            window.addEventListener('scroll', () => { if (!box.classList.contains('hidden')) place(); }, { passive: true });
        }

        // The tab bar is data-turbo-permanent (never re-rendered), so highlight
        // the active tab client-side on every visit.
        function updateTabs() {
            document.querySelectorAll('#app-tabbar a[data-active-when]').forEach(function (a) {
                const active = new RegExp(a.dataset.activeWhen).test(location.pathname);
                a.classList.toggle('text-orange-500', active);
                a.classList.toggle('text-neutral-400', !active);
            });
        }

        // ---- reading-list reader: a paged deck of full posts ----------------
        // Content is fetched per card as it comes into view; advancing past a
        // card marks it read (persisted), the last card is marked on arrival
        // since there is nowhere to advance to. Read cards stay in the deck for
        // the session — they only drop out on the next visit.
        function initReader() {
            const deck = document.querySelector('[data-reader-deck]');
            if (!deck || deck.__init) return;   // element is fresh per navigation
            deck.__init = true;

            const cards = Array.prototype.slice.call(deck.querySelectorAll('[data-reader-card]'));
            const total = cards.length;
            let lastActive = null;

            function loadCard(card) {
                if (!card || card.__loaded || card.__loading) return;
                card.__loading = true;
                fetch(card.dataset.url, { headers: { 'Accept': 'text/html' } })
                    .then(function (r) { if (!r.ok) throw new Error('failed'); return r.text(); })
                    .then(function (html) {
                        const body = card.querySelector('[data-card-body]');
                        if (body) body.innerHTML = html;
                        card.__loaded = true;
                    })
                    .catch(function () {
                        const body = card.querySelector('[data-card-body]');
                        if (body) body.innerHTML = '<p class="text-sm text-red-300 py-8 text-center">Could not load this post.</p>';
                    })
                    .finally(function () { card.__loading = false; });
            }

            function markRead(card) {
                if (!card || card.__read) return;
                card.__read = true;
                const chip = card.querySelector('[data-card-read-chip]');
                if (chip) chip.hidden = false;
                fetch(card.dataset.markUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (state) { if (typeof updateDock === 'function') updateDock(state); })
                    .catch(function () {});
            }

            function setActive(idx) {
                const pos = document.querySelector('[data-reader-position]');
                if (pos) pos.textContent = idx + 1;
                const prog = document.querySelector('[data-reader-progress]');
                if (prog) prog.style.width = ((idx + 1) / total * 100) + '%';

                loadCard(cards[idx]);
                loadCard(cards[idx + 1]);   // prefetch the next so the swipe feels instant

                // You've moved on from the previous card, so it's read.
                if (lastActive !== null && lastActive !== idx) markRead(cards[lastActive]);
                lastActive = idx;

                // The last card is terminal — nothing to advance to, so arriving
                // is finishing it.
                if (cards[idx] && cards[idx].dataset.last === '1') markRead(cards[idx]);
            }

            const io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting && e.intersectionRatio >= 0.6) {
                        setActive(Number(e.target.dataset.index));
                    }
                });
            }, { root: deck, threshold: [0.6] });
            cards.forEach(function (c) { io.observe(c); });

            setActive(0);

            deck.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-reader-next]');
                if (!btn) return;
                const card = btn.closest('[data-reader-card]');
                const next = cards[Number(card.dataset.index) + 1];
                if (next) next.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            });
        }

        function initPage() {
            document.querySelectorAll('input[data-sub-autocomplete]').forEach(initAutocomplete);
            observeLoadMore();
            initReader();
            updateTabs();
            showFlash();
            document.querySelectorAll('video[data-video-hls]').forEach(function (v) {
                setupHlsVideo(v, v.dataset.videoHls, v.dataset.videoFallback);
            });
        }

        // Strip body-appended helpers (dropdowns, toasts) before Turbo caches a
        // snapshot, so restored pages are pristine and get re-initialised clean.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('[data-ac-box],[data-toast],[data-modal]').forEach(el => el.remove());
        });

        document.addEventListener('turbo:load', initPage);
        if (!window.Turbo) document.addEventListener('DOMContentLoaded', initPage);

        // Service worker (installable / offline shell).
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        }
    })();
    </script>

</body>
</html>
