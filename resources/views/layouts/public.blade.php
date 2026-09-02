<!doctype html>
{{-- Guest layout for shared post links: none of the app shell, none of the
     account data, no Turbo. Just the thread, read-only, and a way to log in. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Shared links are for the people they're sent to, not for crawlers. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'reader')</title>

    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png?v=3">
    <meta name="theme-color" content="#efe7d6">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Same warm "paper" remap as the app layout, so shared pages match.
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
                red:     { 200: '#7f1d1d', 300: '#991b1b', 400: '#b91c1c', 700: '#f87171', 900: '#fee2e2', 950: '#fef2f2' },
            } } },
        };
    </script>
    <style>
        html { color-scheme: light; -webkit-text-size-adjust: 100%; }
        body { -webkit-tap-highlight-color: transparent; }
        main { overflow-x: clip; }
        .reddit-html { overflow-wrap: anywhere; word-break: break-word; }
        .reddit-html a { overflow-wrap: anywhere; word-break: break-all; color: #93611c; text-decoration: underline; text-underline-offset: 2px; }
        .reddit-html p { margin: 0.5em 0; }
        .reddit-html blockquote { border-left: 3px solid #cbbb98; padding-left: 0.75em; margin: 0.5em 0; color: #6a6250; }
        .reddit-html pre { background: #f3ecda; padding: 0.5em; border-radius: 0.5em; overflow-x: auto; white-space: pre-wrap; word-break: break-word; }
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
<body class="bg-neutral-950 text-neutral-100 antialiased">
    <header class="sticky top-0 z-10 bg-neutral-950/95 backdrop-blur border-b border-neutral-800"
            style="padding-top: env(safe-area-inset-top);">
        <div class="max-w-2xl mx-auto h-12 px-3 flex items-center justify-between">
            <span class="text-sm font-semibold text-neutral-300">reader</span>
            <a href="{{ route('login') }}"
               class="px-3.5 py-1.5 rounded-full text-sm font-medium text-white bg-orange-500 hover:bg-orange-400">
                Log in
            </a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto"
          style="padding-left: max(0.75rem, env(safe-area-inset-left));
                 padding-right: max(0.75rem, env(safe-area-inset-right));
                 padding-top: 1rem;
                 padding-bottom: calc(env(safe-area-inset-bottom) + 2rem);">
        @yield('content')
    </main>

    <script>
    (function () {
        // ---- collapse/expand a comment — same behaviour as the app layout ---
        function toggleComment(el) {
            const wrap = el.closest('[data-comment-wrap]');
            const c = wrap && wrap.querySelector(':scope > [data-comment]');
            if (!c) return;
            const body = c.querySelector(':scope > .comment-body-wrap > .comment-collapsible');
            if (!body) return;
            const collapsed = body.classList.toggle('hidden');
            const label = c.querySelector(':scope > .comment-head > [data-collapse-btn]');
            if (label) label.textContent = collapsed ? '[+]' : '[-]';
        }

        document.addEventListener('click', function (e) {
            const t = e.target.closest('[data-collapse]');
            if (!t || e.target.closest('a')) return;
            e.preventDefault();
            toggleComment(t);
        });

        // ---- Reddit video with sound (see the app layout for the whole
        // story: the mp4 fallback is the video track alone, HLS muxes audio) --
        const HLS_CDN = 'https://cdn.jsdelivr.net/npm/hls.js@1.7.1/dist/hls.min.js';
        let hlsLib = null;
        function loadHlsLib() {
            return hlsLib ??= new Promise(function (resolve) {
                const s = document.createElement('script');
                s.src = HLS_CDN;
                s.onload = resolve;
                s.onerror = resolve;
                document.head.appendChild(s);
            });
        }

        function setupHlsVideo(el, hlsUrl, fallback) {
            if (!hlsUrl || el.__hlsSetup) return;
            el.__hlsSetup = true;

            if (!('MediaSource' in window)) {
                if (el.canPlayType('application/vnd.apple.mpegurl')) {
                    el.innerHTML = '';
                    el.src = hlsUrl;
                }
                return;
            }

            loadHlsLib().then(function () {
                if (!window.Hls || !Hls.isSupported()) {
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
                if (el.paused) {
                    el.addEventListener('play', function () { h.startLoad(); }, { once: true });
                } else {
                    h.startLoad();
                }
            });
        }

        document.querySelectorAll('video[data-video-hls]').forEach(function (v) {
            setupHlsVideo(v, v.dataset.videoHls, v.dataset.videoFallback);
        });
    })();
    </script>
</body>
</html>
