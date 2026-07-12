<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

/**
 * First-run setup: creates the single owner account and registers the first
 * passkey in one step. Every endpoint here 404s once the users table is
 * non-empty, so the whole flow is dead after setup completes.
 */
class SetupController extends Controller
{
    private const OWNER_NAME = 'Johnathan';

    private const OWNER_EMAIL = 'johnathan@1707.pro';

    private const SESSION_KEY = 'setup-passkey-options';

    public function show()
    {
        $this->ensureSetupIsAvailable();

        return view('auth.setup');
    }

    /**
     * Generate WebAuthn registration options for the owner-to-be. The user
     * row is NOT created yet — it only comes into existence in store(), once
     * the browser ceremony has produced a credential. That way a cancelled
     * ceremony leaves the users table empty and /setup stays available.
     */
    public function options(Request $request): JsonResponse
    {
        $this->ensureSetupIsAvailable();

        $prospectiveOwner = new User([
            'name' => self::OWNER_NAME,
            'email' => self::OWNER_EMAIL,
        ]);

        // The options generator needs a user id for the WebAuthn user handle.
        // The users table is empty, so the row created in store() will get
        // id 1 (SQLite assigns max(rowid)+1). Login does not depend on the
        // handle anyway — passkeys are looked up by credential id.
        $prospectiveOwner->id = 1;

        /** @var GeneratePasskeyRegisterOptionsAction $generateOptions */
        $generateOptions = Config::getAction(
            'generate_passkey_register_options',
            GeneratePasskeyRegisterOptionsAction::class,
        );

        $optionsJson = $generateOptions->execute($prospectiveOwner);

        $request->session()->put(self::SESSION_KEY, $optionsJson);

        return response()->json(json_decode($optionsJson));
    }

    /**
     * Verify the attestation, create the owner, attach the passkey, log in.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureSetupIsAvailable();

        $request->validate(['passkey' => ['required', 'string']]);

        $optionsJson = $request->session()->pull(self::SESSION_KEY);

        if (blank($optionsJson)) {
            return response()->json([
                'message' => 'Setup session expired. Please try again.',
            ], 422);
        }

        /** @var StorePasskeyAction $storePasskey */
        $storePasskey = Config::getAction('store_passkey', StorePasskeyAction::class);

        try {
            $user = DB::transaction(function () use ($request, $storePasskey, $optionsJson): User {
                $user = User::create([
                    'name' => self::OWNER_NAME,
                    'email' => self::OWNER_EMAIL,
                    // Passkey-only auth: no password login exists, but the
                    // column is NOT NULL, so store a random throwaway.
                    'password' => Str::random(64),
                ]);

                $storePasskey->execute(
                    $user,
                    $request->input('passkey'),
                    $optionsJson,
                    $request->getHost(),
                    ['name' => 'First passkey'],
                );

                return $user;
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The passkey could not be verified. Please try again.',
            ], 422);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['redirect' => url('/')]);
    }

    private function ensureSetupIsAvailable(): void
    {
        abort_unless(User::query()->count() === 0, 404);
    }
}
