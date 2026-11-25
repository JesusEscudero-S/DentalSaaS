<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Módulo 1 (diagrama 3): al restablecer la contraseña de un usuario se cierran sus sesiones abiertas.
 *
 * La sesión guarda [usuario, hash de su contraseña]. Si el hash del mismo usuario cambió desde que
 * inició sesión, la sesión deja de valer. (El AuthenticateSession de Laravel hace algo parecido, pero
 * no distingue al usuario y cierra la sesión cuando una prueba cambia de usuario con actingAs).
 */
class EnsureSessionIsCurrent
{
    public const KEY = 'account_password_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        [$userId, $hash] = $request->session()->get(self::KEY, [null, null]);

        if ($userId === $user->getAuthIdentifier() && ! hash_equals((string) $hash, (string) $user->getAuthPassword())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu contraseña cambió. Inicia sesión de nuevo.']);
        }

        self::remember($request);

        return $next($request);
    }

    /** Guarda el hash vigente (al iniciar sesión o después de que el usuario cambia su propia contraseña). */
    public static function remember(Request $request): void
    {
        $user = $request->user();
        $request->session()->put(self::KEY, [$user->getAuthIdentifier(), $user->getAuthPassword()]);
    }
}
