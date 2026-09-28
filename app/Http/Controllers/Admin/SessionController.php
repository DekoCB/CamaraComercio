<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sesiones activas — SESSION_DRIVER=database ya guarda una fila por
 * sesión con quién es (user_id) y cuándo se movió por última vez
 * (last_activity); lo único que ese renglón no dice es CUÁNDO empezó,
 * así que "hace cuánto" se calcula del último auth.login exitoso en
 * AuditLog para ese usuario, no de la tabla sessions.
 */
class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $cutoff = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoff)
            ->orderByDesc('last_activity')
            ->get();

        $userIds = $sessions->pluck('user_id')->unique()->all();
        $users = User::with('role')->whereIn('id', $userIds)->get()->keyBy('id');

        $lastLogins = AuditLog::query()
            ->where('action', 'auth.login')
            ->where('result', 'success')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($logs) => $logs->first()->created_at);

        $currentId = $request->session()->getId();

        $rows = $sessions
            ->map(function ($session) use ($users, $lastLogins, $currentId) {
                $user = $users->get($session->user_id);
                if (! $user) {
                    return null;
                }

                return (object) [
                    'id' => $session->id,
                    'user' => $user,
                    'loggedInAt' => $lastLogins->get($session->user_id),
                    'lastActivity' => CarbonImmutable::createFromTimestamp($session->last_activity),
                    'ipAddress' => $session->ip_address,
                    'userAgent' => $session->user_agent,
                    'isCurrent' => $session->id === $currentId,
                ];
            })
            ->filter()
            ->values();

        return view('admin.sessions.index', ['sessions' => $rows]);
    }

    /**
     * Borra la fila de sessions — no hay "cerrar sesión" server-side más
     * directo que eso con SESSION_DRIVER=database: en su siguiente
     * request, ese navegador ya no tiene una sesión válida que retomar.
     */
    public function destroy(string $id): RedirectResponse
    {
        DB::table('sessions')->where('id', $id)->delete();

        AuditLog::record('admin.session.close', 'session', $id, 'success');

        return back()->with('success', 'Sesión cerrada.');
    }
}
