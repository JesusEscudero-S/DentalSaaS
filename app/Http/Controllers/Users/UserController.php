<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Services\Users\UserService;
use App\Support\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Módulo 1. Gestión de usuarios. */
class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /** Diagrama 2, paso 2: lista con búsqueda y filtros por rol y estatus. */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('users.index', [
            'members' => $this->filteredQuery($filters)
                ->with(['user', 'role', 'doctorProfile'])
                ->paginate(8)
                ->withQueryString(),
            'filters' => $filters,
            'roles' => $this->roles(),
            'activeCount' => OrganizationMember::where('is_active', true)->count(),
        ]);
    }

    /** Diagrama 1. */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        [$member, $password] = $this->users->create($request->validated());

        return redirect()
            ->route('users.index', ['q' => $member->user->email, 'estado' => 'todos'])
            ->with('toast', ['type' => 'success', 'title' => 'Usuario registrado exitosamente', 'message' => $member->user->full_name])
            ->with('temp_password', ['title' => 'Usuario registrado', 'name' => $member->user->full_name, 'email' => $member->user->email, 'password' => $password]);
    }

    /** Diagrama 2. */
    public function update(UpdateUserRequest $request, OrganizationMember $member): RedirectResponse
    {
        return $this->attempt(function () use ($request, $member) {
            $this->users->update($member, $request->validated());

            return back()->with('toast', ['type' => 'success', 'title' => 'Usuario actualizado correctamente', 'message' => $member->user->full_name]);
        });
    }

    /** Diagrama 3. */
    public function deactivate(OrganizationMember $member): RedirectResponse
    {
        return $this->attempt(function () use ($member) {
            $this->users->deactivate($member);

            return back()->with('toast', ['type' => 'success', 'title' => 'Usuario desactivado', 'message' => $member->user->full_name]);
        });
    }

    public function reactivate(OrganizationMember $member): RedirectResponse
    {
        return $this->attempt(function () use ($member) {
            $this->users->reactivate($member);

            return back()->with('toast', ['type' => 'success', 'title' => 'Usuario reactivado', 'message' => $member->user->full_name]);
        });
    }

    /** Diagrama 3, rama "Restablecer contraseña". */
    public function resetPassword(OrganizationMember $member): RedirectResponse
    {
        return $this->attempt(function () use ($member) {
            $password = $this->users->resetPassword($member);

            return back()
                ->with('toast', ['type' => 'success', 'title' => 'Contraseña restablecida', 'message' => $member->user->full_name])
                ->with('temp_password', ['title' => 'Contraseña restablecida', 'name' => $member->user->full_name, 'email' => $member->user->email, 'password' => $password]);
        });
    }

    /** "Salida en pantalla": la lista se puede imprimir y descargar en PDF. */
    public function pdf(Request $request): Response
    {
        $filters = $this->filters($request);

        $pdf = Pdf::loadView('users.pdf', [
            'organization' => Organization::find(app(CurrentOrganization::class)->id()),
            'members' => $this->filteredQuery($filters)->with(['user', 'role'])->get(),
            'filters' => $filters,
            'roleName' => $filters['rol'] !== '' ? Role::find($filters['rol'])?->name : null,
        ])->setPaper('letter');

        return $pdf->download('usuarios-'.now()->format('Y-m-d').'.pdf');
    }

    /** @return array{q: string, rol: string, estado: string} */
    private function filters(Request $request): array
    {
        $estado = $request->query('estado', 'activos');

        return [
            'q' => trim((string) $request->query('q', '')),
            'rol' => (string) $request->query('rol', ''),
            'estado' => in_array($estado, ['activos', 'inactivos', 'todos'], true) ? $estado : 'activos',
        ];
    }

    /** @param  array{q: string, rol: string, estado: string}  $filters */
    private function filteredQuery(array $filters): Builder
    {
        $like = '%'.addcslashes($filters['q'], '%_\\').'%';

        return OrganizationMember::query()
            ->join('users', 'users.id', '=', 'organization_members.user_id')
            ->select('organization_members.*')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('users.full_name', 'like', $like)
                ->orWhere('users.email', 'like', $like)))
            ->when($filters['rol'] !== '', fn ($q) => $q->where('organization_members.role_id', $filters['rol']))
            ->when($filters['estado'] === 'activos', fn ($q) => $q->where('organization_members.is_active', true))
            ->when($filters['estado'] === 'inactivos', fn ($q) => $q->where('organization_members.is_active', false))
            ->orderBy('users.full_name');
    }

    private function roles()
    {
        return Role::where('status', 'active')->orderBy('name')->get();
    }

    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $e->getMessage()]);
        }
    }
}
