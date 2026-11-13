<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una sola clínica: busca la membresía activa del usuario en una organización activa
 * (la más antigua si tuviera varias) y la deja en CurrentOrganization.
 * Si el usuario no tiene acceso, cierra la sesión.
 *
 * Importante: en Kernel::$middlewarePriority corre ANTES de SubstituteBindings,
 * para que el route model binding ya filtre por la clínica.
 */
class ResolveOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $member = OrganizationMember::query()
            ->with(['user', 'role'])
            ->join('organizations', 'organizations.id', '=', 'organization_members.organization_id')
            ->where('organization_members.user_id', Auth::id())
            ->where('organization_members.is_active', true)
            ->where('organizations.status', 'active')
            ->orderBy('organizations.created_at')
            ->select('organization_members.*')
            ->first();

        if (! $member || ! $member->user?->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu usuario no tiene acceso activo a la clínica.']);
        }

        $this->current->set($member);

        return $next($request);
    }
}
