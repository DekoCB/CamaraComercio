<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // HU-03: never reveal whether the email exists. Laravel's
        // Password broker already returns a generic status either way
        // (RESET_LINK_SENT vs INVALID_USER); we show the same message
        // to the visitor regardless of which one comes back.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.');
    }
}
