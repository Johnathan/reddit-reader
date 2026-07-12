@extends('auth.layout')

@section('title', 'Sign in')

@section('content')
<main class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-orange-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                </div>
                <h1 class="text-xl font-semibold tracking-tight">Welcome back</h1>
                <p class="mt-1 text-sm text-zinc-400">Sign in to your Reddit reader</p>
            </div>

            @if ($message = session('authenticatePasskey::message'))
                <div class="mb-4 rounded-lg border border-red-900 bg-red-950/60 px-4 py-3 text-sm text-red-300">
                    {{ $message }}
                </div>
            @endif

            <div id="error-message" class="mb-4 hidden rounded-lg border border-red-900 bg-red-950/60 px-4 py-3 text-sm text-red-300"></div>

            <button id="sign-in-button" onclick="signInWithPasskey()"
                class="w-full rounded-xl bg-orange-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:cursor-not-allowed disabled:opacity-50">
                Sign in with passkey
            </button>
        </div>
    </div>
</main>

{{-- The package's authenticate endpoint expects a classic form POST. --}}
<form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}" class="hidden">
    @csrf
    <input type="hidden" name="remember" value="1">
    <input type="hidden" name="start_authentication_response" id="start_authentication_response">
</form>
@endsection

@section('scripts')
<script>
    async function signInWithPasskey() {
        clearError();

        const button = document.getElementById('sign-in-button');
        button.disabled = true;

        try {
            const options = await fetchJson('{{ route('passkeys.authentication_options') }}');

            const assertion = await SimpleWebAuthnBrowser.startAuthentication({ optionsJSON: options });

            document.getElementById('start_authentication_response').value = JSON.stringify(assertion);
            document.getElementById('passkey-login-form').submit();
        } catch (error) {
            if (error.name !== 'NotAllowedError') {
                showError(error.message ?? 'Sign in failed. Please try again.');
            }
            button.disabled = false;
        }
    }
</script>
@endsection
