<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Carga la organización activa (guardada en sesión al iniciar sesión) y la membresía
 * del usuario. Si ya no es válida (miembro u organización inactivos), cierra la sesión.
 */
class ResolveOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $member = OrganizationMember::query()
            ->with(['role', 'user'])
            ->where('user_id', Auth::id())
            ->where('organization_id', $request->session()->get('organization_id'))
            ->where('is_active', true)
            ->whereHas('organization', fn ($q) => $q->where('status', 'active'))
            ->first();

        if (! $member || ! $member->user?->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        $this->current->set($member);

        return $next($request);
    }
}
