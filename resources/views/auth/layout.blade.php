<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>

    {{-- PWA meta, mirrored from the app layout so launching to /login still
         styles the status bar and offers install with the right icon. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Reddit Reader">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@simplewebauthn/browser@13.1.0/dist/bundle/index.umd.min.js"></script>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">

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
