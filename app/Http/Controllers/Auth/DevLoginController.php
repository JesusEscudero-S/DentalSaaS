<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Acceso temporal para desarrollo: permite entrar como cualquier miembro activo sembrado.
 * Sus rutas solo se registran con APP_ENV=local/testing. Se reemplaza en el módulo 1.
 */
class DevLoginController extends Controller
{
    public function show(): View
    {
        $members = OrganizationMember::query()
            ->with(['user', 'role', 'organization'])
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn ($m) => $m->organization->commercial_name.$m->user->full_name);

        return view('auth.dev-login', ['members' => $members]);
    }

    public function login(Request $request, string $member): RedirectResponse
    {
        $member = OrganizationMember::with('user')->where('is_active', true)->findOrFail($member);

        Auth::login($member->user);
        $request->session()->regenerate();
        $request->session()->put('organization_id', $member->organization_id);

        $member->user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('patients.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
