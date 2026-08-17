<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['token' => $request->route('token'), 'email' => $request->query('email', '')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // Password::reset already enforces single use (the token record is
        // deleted on success) and expiry (config('auth.passwords.users.expire'),
        // 60 minutes by default) — see HU-03.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill(['password' => Hash::make($request->password)])->save();
                event(new PasswordReset($user));
                AuditLog::record('auth.password_reset_completed', 'user', (string) $user->id);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'El enlace de restablecimiento no es válido o expiró. Solicite uno nuevo.']);
        }

        return redirect()->route('login')->with('status', 'Su contraseña fue actualizada. Ya puede iniciar sesión.');
    }
}
