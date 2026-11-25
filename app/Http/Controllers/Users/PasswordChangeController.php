<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * RN-U6 (diagrama 4, pasos 4-6) y P7 "Mi perfil": cada usuario cambia su propia contraseña.
 * Si tiene contraseña temporal, el middleware password.changed lo trae aquí antes que a cualquier módulo.
 */
class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('users.change-password', ['forced' => (bool) Auth::user()->must_change_password]);
    }

    public function update(ChangePasswordRequest $request, AuditLogger $audit, CurrentOrganization $current): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $audit, $current) {
            $user->update(['password' => $request->validated('password'), 'must_change_password' => false]);
            $audit->log($current->member(), AuditLogger::UPDATE, ['password' => 'changed']);
        });

        // session.current compara el hash guardado en la sesión: se actualiza para no cerrar esta sesión.
        EnsureSessionIsCurrent::remember($request);

        return redirect()->route('home')
            ->with('toast', ['type' => 'success', 'title' => 'Contraseña actualizada', 'message' => 'Usa tu nueva contraseña la próxima vez que entres.']);
    }
}
