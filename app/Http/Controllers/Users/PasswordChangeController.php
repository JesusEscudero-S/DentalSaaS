<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P7 "Mi perfil": cada usuario cambia su propia contraseña (por ejemplo, la temporal que le dio el administrador).
 */
class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('users.change-password');
    }

    public function update(ChangePasswordRequest $request, AuditLogger $audit, CurrentOrganization $current): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $audit, $current) {
            $user->update(['password' => $request->validated('password')]);
            $audit->log($current->member(), AuditLogger::UPDATE, ['password' => 'changed']);
        });

        // session.current compara el hash guardado en la sesión: se actualiza para no cerrar esta sesión.
        EnsureSessionIsCurrent::remember($request);

        return redirect()->route('home')
            ->with('toast', ['type' => 'success', 'title' => 'Contraseña actualizada', 'message' => 'Usa tu nueva contraseña la próxima vez que entres.']);
    }
}
