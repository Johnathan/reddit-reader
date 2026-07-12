<?php

use App\Models\User;
use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

return [
    /*
     * After a successful authentication attempt using a passkey
     * we'll redirect to this URL.
     */
    'redirect_to_after_login' => '/',

    /*
     * These class are responsible for performing core tasks regarding passkeys.
     * You can customize them by creating a class that extends the default, and
     * by specifying your custom class name here.
     */
    'actions' => [
        'generate_passkey_register_options' => GeneratePasskeyRegisterOptionsAction::class,
        'store_passkey' => StorePasskeyAction::class,
        'generate_passkey_authentication_options' => GeneratePasskeyAuthenticationOptionsAction::class,
        'find_passkey' => FindPasskeyToAuthenticateAction::class,
        'configure_ceremony_step_manager_factory' => ConfigureCeremonyStepManagerFactoryAction::class,
    ],

    /*
     * These properties will be used to generate the passkey.
     *
     * The relying-party ID is derived from APP_URL (its hostname). WebAuthn
     * requires a "secure context": http://localhost qualifies, so the app
     * works out of the box with APP_URL=http://localhost:8000 and
     * `php artisan serve`.
     *
     * When serving on a LAN / Tailscale hostname later:
     *   1. Set APP_URL to that origin, e.g. https://reddit.tailnet-name.ts.net
     *      (it MUST be https:// — browsers refuse WebAuthn on plain http
     *      anywhere other than localhost).
     *   2. Passkeys are bound to the relying-party ID (hostname), so keys
     *      registered on "localhost" will NOT work on the new hostname —
     *      re-register passkeys from /settings/passkeys after moving.
     */
    'relying_party' => [
        'name' => config('app.name'),
        'id' => parse_url(config('app.url'), PHP_URL_HOST),
        'icon' => null,
    ],

    /*
     * The models used by the package.
     *
     * You can override this by specifying your own models
     */
    'models' => [
        'passkey' => Passkey::class,
        'authenticatable' => env('AUTH_MODEL', User::class),
    ],
];
