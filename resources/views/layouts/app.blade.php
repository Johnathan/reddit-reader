<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
        // Warm "paper" theme. The UI was authored dark-first with neutral/orange
        // utility classes, so instead of editing every view we remap those
        // palettes to a light cream/navy/amber scheme: the neutral ramp is
        // inverted (950 = cream page, 100 = navy ink), orange becomes amber,
        // and the semantic scales are inverted so *-900 backgrounds read as
        // light tints with dark *-200 text.
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
    <style>
        html { color-scheme: light; -webkit-text-size-adjust: 100%; }
        body { -webkit-tap-highlight-color: transparent; }

        /* iOS Safari zooms the page when you focus a form control whose
           font-size is < 16px. Every input here is text-sm (14px), so pin
           form controls to 16px. !important is needed to beat Tailwind's
           .text-sm class. Applied on touch/small screens only, so the desktop
           layout keeps its compact type. */
        @media (max-width: 767px) {
            input:not([type="checkbox"]):not([type="radio"]),
            textarea,
            select { font-size: 16px !important; }
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
<body class="bg-neutral-950 text-neutral-100 min-h-screen antialiased">
    @include('partials.nav')

    <main class="max-w-2xl mx-auto pt-4"
          style="padding-left: max(0.75rem, env(safe-area-inset-left)); padding-right: max(0.75rem, env(safe-area-inset-right)); padding-bottom: calc(6rem + env(safe-area-inset-bottom));">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-3 py-2 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

    <script>
    // Live subreddit typeahead. Attach to any text input with
    // data-sub-autocomplete="navigate" (go to the subreddit on pick) or
    // "fill" (just drop the name into the field). No dependencies.
    (function () {
        const SUGGEST_URL = @json(route('subs.suggest'));
        const fmt = n => n >= 1e6 ? (n/1e6).toFixed(1)+'m' : n >= 1e3 ? (n/1e3).toFixed(1)+'k' : ''+n;

        document.querySelectorAll('input[data-sub-autocomplete]').forEach(function (input) {
            const mode = input.getAttribute('data-sub-autocomplete') || 'fill';
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('spellcheck', 'false');

            const box = document.createElement('div');
            box.className = 'absolute left-0 right-0 mt-1 z-30 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl overflow-hidden hidden';
            const wrap = document.createElement('div');
            wrap.className = 'relative flex-1';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            wrap.appendChild(box);
            input.classList.add('w-full'); // Fill the wrapper; the form's flex-1 now lives on the wrapper.

            let items = [], active = -1, seq = 0, timer = null;

            function hide() { box.classList.add('hidden'); box.innerHTML = ''; items = []; active = -1; }

            function choose(name) {
                if (mode === 'navigate') {
                    window.location.href = '/r/' + name.toLowerCase();
                } else {
                    input.value = name;
                    hide();
                    input.focus();
                }
            }

            function render() {
                box.innerHTML = '';
                items.forEach(function (s, i) {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-neutral-800 ' + (i === active ? 'bg-neutral-800' : '');
                    const icon = s.icon
                        ? '<img src="' + s.icon + '" class="w-5 h-5 rounded-full shrink-0" alt="">'
                        : '<span class="w-5 h-5 rounded-full bg-neutral-700 shrink-0"></span>';
                    row.innerHTML = icon +
                        '<span class="text-sm text-neutral-100 truncate">r/' + s.name + '</span>' +
                        (s.subscribers ? '<span class="ml-auto text-xs text-neutral-500 shrink-0">' + fmt(s.subscribers) + '</span>' : '');
                    row.addEventListener('mousedown', e => { e.preventDefault(); choose(s.name); });
                    box.appendChild(row);
                });
                box.classList.toggle('hidden', items.length === 0);
            }

            async function query(q) {
                const mine = ++seq;
                try {
                    const res = await fetch(SUGGEST_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (mine !== seq) return; // A newer keystroke already fired; drop this stale response.
                    items = Array.isArray(data) ? data : [];
                    active = -1;
                    render();
                } catch (e) { /* network hiccup: leave the box as-is */ }
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

            input.addEventListener('blur', () => setTimeout(hide, 120));
        });
    })();
    </script>

    <script>
    // Register the service worker so the app is installable to the home screen.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }
    </script>
</body>
</html>
