@extends('auth.layout')

@section('title', 'Passkeys')

@section('content')
<main class="mx-auto w-full max-w-2xl p-4 sm:p-8">
    <header class="mb-8 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Passkeys</h1>
            <p class="mt-1 text-sm text-zinc-400">Devices that can sign in to this app.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url('/') }}"
                class="rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-300 transition hover:bg-zinc-900">
                Home
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-300 transition hover:bg-zinc-900">
                    Log out
                </button>
            </form>
        </div>
    </header>

    @if ($status = session('status'))
        <div class="mb-4 rounded-lg border border-emerald-900 bg-emerald-950/60 px-4 py-3 text-sm text-emerald-300">
            {{ $status }}
        </div>
    @endif

    @if ($error = session('error'))
        <div class="mb-4 rounded-lg border border-red-900 bg-red-950/60 px-4 py-3 text-sm text-red-300">
            {{ $error }}
        </div>
    @endif

    <div id="error-message" class="mb-4 hidden rounded-lg border border-red-900 bg-red-950/60 px-4 py-3 text-sm text-red-300"></div>

    <section class="mb-8 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900">
        <ul class="divide-y divide-zinc-800">
            @forelse ($passkeys as $passkey)
                <li class="flex items-center justify-between gap-4 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $passkey->name }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            Added {{ $passkey->created_at->diffForHumans() }}
                            &middot;
                            {{ $passkey->last_used_at ? 'Last used '.$passkey->last_used_at->diffForHumans() : 'Never used' }}
                        </p>
                    </div>
                    @if ($passkeys->count() > 1)
                        <form method="POST" action="{{ route('settings.passkeys.destroy', $passkey->id) }}"
                            onsubmit="return confirm('Delete passkey &quot;{{ $passkey->name }}&quot;? The device using it will no longer be able to sign in.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="rounded-lg border border-red-900/60 px-3 py-1.5 text-xs font-medium text-red-400 transition hover:bg-red-950/60">
                                Delete
                            </button>
                        </form>
                    @else
                        <span class="cursor-not-allowed rounded-lg border border-zinc-800 px-3 py-1.5 text-xs text-zinc-600"
                            title="You can't delete your last passkey — you would be locked out.">
                            Last key
                        </span>
                    @endif
                </li>
            @empty
                <li class="p-4 text-sm text-zinc-500">No passkeys registered.</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
        <h2 class="text-sm font-semibold">Add a passkey</h2>
        <p class="mt-1 text-xs text-zinc-500">
            Run this on the device you want to enroll (e.g. your phone or laptop).
        </p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input id="passkey-name" type="text" maxlength="255" placeholder="Device name, e.g. iPhone"
                class="w-full flex-1 rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-2.5 text-sm placeholder-zinc-600 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500">
            <button id="add-passkey-button" onclick="addPasskey()"
                class="rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:cursor-not-allowed disabled:opacity-50">
                Register passkey
            </button>
        </div>
    </section>
</main>
@endsection

@section('scripts')
<script>
    async function addPasskey() {
        clearError();

        const nameInput = document.getElementById('passkey-name');
        const name = nameInput.value.trim();

        if (!name) {
            showError('Give the passkey a name first (e.g. "iPhone").');
            nameInput.focus();
            return;
        }

        const button = document.getElementById('add-passkey-button');
        button.disabled = true;

        try {
            await registerPasskey({
                optionsUrl: '{{ route('settings.passkeys.options') }}',
                storeUrl: '{{ route('settings.passkeys.store') }}',
                extra: { name },
            });
        } catch (error) {
            if (error.name !== 'NotAllowedError') {
                showError(error.message ?? 'Registration failed. Please try again.');
            }
            button.disabled = false;
        }
    }
</script>
@endsection
