<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>

    {{-- PWA meta, mirrored from the app layout so launching to /login still
         styles the status bar and offers install with the right icon. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#efe7d6">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Reddit Reader">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@simplewebauthn/browser@13.1.0/dist/bundle/index.umd.min.js"></script>
    <script>
        // Match the app's warm paper theme (see layouts/app.blade.php).
        const paper = {
            100: '#1e2740', 200: '#293251', 300: '#46506b', 400: '#6a6250',
            500: '#7c705a', 600: '#9a8c70', 700: '#cbbb98', 800: '#e5dcc7',
            900: '#fbf8f1', 950: '#efe7d6',
        };
        tailwind.config = {
            theme: { extend: { colors: {
                zinc: paper, neutral: paper,
                orange: { 300: '#b8843a', 400: '#a8722a', 500: '#93611c', 600: '#7d5115' },
                red: { 200: '#7f1d1d', 300: '#991b1b', 400: '#b91c1c', 700: '#f87171', 900: '#fee2e2', 950: '#fef2f2' },
            } } },
        };
    </script>
    <style>
        html { color-scheme: light; -webkit-text-size-adjust: 100%; }
        /* Prevent iOS focus-zoom (font-size must be >= 16px on form controls). */
        @media (max-width: 767px) {
            input:not([type="checkbox"]):not([type="radio"]),
            textarea, select { font-size: 16px !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased"
      style="padding: env(safe-area-inset-top) max(0px, env(safe-area-inset-right)) env(safe-area-inset-bottom) max(0px, env(safe-area-inset-left));">

    @yield('content')

    <script>
        async function fetchJson(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(options.headers ?? {}),
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message ?? 'Something went wrong. Please try again.');
            }

            return data;
        }

        // Full registration ceremony: fetch options, run WebAuthn, post result.
        async function registerPasskey({ optionsUrl, storeUrl, extra = {} }) {
            if (!window.SimpleWebAuthnBrowser?.browserSupportsWebAuthn()) {
                throw new Error('This browser does not support passkeys.');
            }

            const options = await fetchJson(optionsUrl);

            const credential = await SimpleWebAuthnBrowser.startRegistration({ optionsJSON: options });

            const result = await fetchJson(storeUrl, {
                method: 'POST',
                body: JSON.stringify({ passkey: JSON.stringify(credential), ...extra }),
            });

            if (result.redirect) {
                window.location.assign(result.redirect);
            }
        }

        function showError(message) {
            const el = document.getElementById('error-message');
            el.textContent = message;
            el.classList.remove('hidden');
        }

        function clearError() {
            document.getElementById('error-message')?.classList.add('hidden');
        }
    </script>
    @yield('scripts')
</body>
</html>
