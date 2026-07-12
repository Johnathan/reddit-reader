@extends('auth.layout')

@section('title', 'First-run setup')

@section('content')
<main class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-orange-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h1 class="text-xl font-semibold tracking-tight">Set up your account</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    One-time setup: this creates the owner account and registers this device's passkey.
                </p>
            </div>

            <dl class="mb-6 space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/50 p-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Name</dt>
                    <dd class="font-medium">Johnathan</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Email</dt>
                    <dd class="font-medium">johnathan&#64;1707.pro</dd>
                </div>
            </dl>

            <div id="error-message" class="mb-4 hidden rounded-lg border border-red-900 bg-red-950/60 px-4 py-3 text-sm text-red-300"></div>

            <button id="setup-button" onclick="runSetup()"
                class="w-full rounded-xl bg-orange-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:cursor-not-allowed disabled:opacity-50">
                Create account &amp; register passkey
            </button>

            <p class="mt-4 text-center text-xs text-zinc-500">
                You'll be signed in automatically afterwards.
            </p>
        </div>
    </div>
</main>
@endsection

@section('scripts')
<script>
    async function runSetup() {
        clearError();

        const button = document.getElementById('setup-button');
        button.disabled = true;

        try {
            await registerPasskey({
                optionsUrl: '{{ route('setup.options') }}',
                storeUrl: '{{ route('setup.store') }}',
            });
        } catch (error) {
            if (error.name !== 'NotAllowedError') {
                showError(error.message ?? 'Setup failed. Please try again.');
            }
            button.disabled = false;
        }
    }
</script>
@endsection
