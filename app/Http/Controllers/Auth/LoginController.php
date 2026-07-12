<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the passkey-only login page.
     */
    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect('/');
        }

        // Nothing to log in with yet — send the owner to first-run setup.
        if (User::query()->count() === 0) {
            return redirect()->route('setup');
        }

        // Carry the auth middleware's intended URL into the passkey flow so
        // the package redirects there after a successful ceremony.
        if ($intended = $request->session()->get('url.intended')) {
            $request->session()->put('passkeys.redirect', $intended);
        }

        return view('auth.login');
    }

    /**
     * Log the user out and return to the login screen.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
