<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // The single "email" field accepts either the email address or the
        // short username; anything without "@" is treated as a username.
        $login = $request->string('email')->trim()->toString();
        $field = str_contains($login, '@') ? 'email' : 'username';
        $credentials = [$field => $login, 'password' => $request->input('password')];

        // Laravel's Auth::attempt already runs password_verify against a
        // real hash only when the user exists; when it doesn't, it still
        // takes a comparable code path (no early return before hashing),
        // so login timing does not leak whether an email is registered.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record('auth.login', 'user', $login, 'failure');

            return back()->withErrors([
                'email' => 'Usuario o contraseña incorrectos.',
            ])->onlyInput('email');
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Usuario o contraseña incorrectos.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditLog::record('auth.login', 'user', (string) Auth::id(), 'success');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
