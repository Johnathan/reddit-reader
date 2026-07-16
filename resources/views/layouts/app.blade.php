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
        html { color-scheme: light; -webkit-text-size-adjust: 100%; }
        body { -webkit-tap-highlight-color: transparent; overscroll-behavior-y: none; }

        /* iOS Safari zooms the page when focusing a form control with
           font-size < 16px; pin form controls to 16px on small screens. */
        @media (max-width: 767px) {
            input:not([type="checkbox"]):not([type="radio"]),
            textarea,
            select { font-size: 16px !important; }
        }

        /* Native-feeling page transitions + Turbo's loading bar. */
        .turbo-progress-bar { height: 3px; background: #93611c; }
        @media (prefers-reduced-motion: no-preference) {
            ::view-transition-old(root),
            ::view-transition-new(root) { animation-duration: 0.2s; }
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
    </style>
</head>
<body class="bg-neutral-950 text-neutral-100 min-h-screen antialiased"
      @if (session('status') || session('error')) data-flash="{{ session('error') ?: session('status') }}" data-flash-error="{{ session('error') ? '1' : '0' }}" @endif>
    @include('partials.nav')

    <main class="max-w-2xl mx-auto pt-4"
          style="padding-left: max(0.75rem, env(safe-area-inset-left)); padding-right: max(0.75rem, env(safe-area-inset-right)); padding-bottom: calc(6rem + env(safe-area-inset-bottom));">
        @yield('content')
    </main>

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

        // ---- collapse/expand a comment (its OWN subtree, not an ancestor's) --
        window.toggleComment = function (btn) {
            const c = btn.closest('[data-comment]');
            if (!c) return;
            const body = c.querySelector(':scope > .comment-body-wrap > .comment-collapsible');
            if (!body) return;
            const collapsed = body.classList.toggle('hidden');
            btn.textContent = collapsed ? '[+]' : '[-]';
        };

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
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
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

                if (form.dataset.ajaxRemove) {
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

        function initPage() {
            document.querySelectorAll('input[data-sub-autocomplete]').forEach(initAutocomplete);
            observeLoadMore();
            showFlash();
        }

        // Strip body-appended helpers (dropdowns, toasts) before Turbo caches a
        // snapshot, so restored pages are pristine and get re-initialised clean.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('[data-ac-box],[data-toast]').forEach(el => el.remove());
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
