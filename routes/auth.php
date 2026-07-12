<?php

// Passkey auth routes — owned by the auth build.

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeySettingsController;
use App\Http\Controllers\Auth\SetupController;
use Illuminate\Support\Facades\Route;

/*
 * Package routes (spatie/laravel-passkeys):
 *   GET  /passkeys/authentication-options  -> passkeys.authentication_options
 *   POST /passkeys/authenticate            -> passkeys.login
 */
Route::passkeys();

// Passkey-only login. Named 'login' so the auth middleware redirect works.
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
 * First-run setup — only available while the users table is empty
 * (the controller 404s otherwise).
 */
Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::get('/setup/options', [SetupController::class, 'options'])->name('setup.options');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// Passkey management for the logged-in owner.
Route::middleware('auth')->prefix('settings/passkeys')->group(function (): void {
    Route::get('/', [PasskeySettingsController::class, 'index'])->name('settings.passkeys');
    Route::get('/options', [PasskeySettingsController::class, 'options'])->name('settings.passkeys.options');
    Route::post('/', [PasskeySettingsController::class, 'store'])->name('settings.passkeys.store');
    Route::delete('/{passkey}', [PasskeySettingsController::class, 'destroy'])
        ->whereNumber('passkey')
        ->name('settings.passkeys.destroy');
});
