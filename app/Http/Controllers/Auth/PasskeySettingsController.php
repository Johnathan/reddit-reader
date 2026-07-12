<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

/**
 * Manage the owner's registered passkeys (list / add / delete).
 * All routes sit behind the auth middleware.
 */
class PasskeySettingsController extends Controller
{
    private const SESSION_KEY = 'passkey-registration-options';

    public function index(Request $request)
    {
        return view('auth.passkeys', [
            'passkeys' => $request->user()->passkeys()->latest()->get(),
        ]);
    }

    /**
     * Generate WebAuthn registration options for enrolling another device.
     */
    public function options(Request $request): JsonResponse
    {
        /** @var GeneratePasskeyRegisterOptionsAction $generateOptions */
        $generateOptions = Config::getAction(
            'generate_passkey_register_options',
            GeneratePasskeyRegisterOptionsAction::class,
        );

        $optionsJson = $generateOptions->execute($request->user());

        $request->session()->put(self::SESSION_KEY, $optionsJson);

        return response()->json(json_decode($optionsJson));
    }

    /**
     * Verify the attestation from the browser ceremony and store the passkey.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'passkey' => ['required', 'string'],
        ]);

        $optionsJson = $request->session()->pull(self::SESSION_KEY);

        if (blank($optionsJson)) {
            return response()->json([
                'message' => 'The registration session expired. Please try again.',
            ], 422);
        }

        /** @var StorePasskeyAction $storePasskey */
        $storePasskey = Config::getAction('store_passkey', StorePasskeyAction::class);

        try {
            $storePasskey->execute(
                $request->user(),
                $validated['passkey'],
                $optionsJson,
                $request->getHost(),
                ['name' => $validated['name']],
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The passkey could not be verified. Please try again.',
            ], 422);
        }

        $request->session()->flash('status', "Passkey \"{$validated['name']}\" registered.");

        return response()->json(['redirect' => route('settings.passkeys')]);
    }

    /**
     * Delete a passkey — but never the last one, or the owner is locked out.
     */
    public function destroy(Request $request, int $passkeyId): RedirectResponse
    {
        $user = $request->user();

        $passkey = $user->passkeys()->findOrFail($passkeyId);

        if ($user->passkeys()->count() <= 1) {
            return redirect()
                ->route('settings.passkeys')
                ->with('error', 'You cannot delete your last passkey — you would be locked out.');
        }

        $passkey->delete();

        return redirect()
            ->route('settings.passkeys')
            ->with('status', "Passkey \"{$passkey->name}\" deleted.");
    }
}
