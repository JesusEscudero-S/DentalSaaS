<?php

namespace App\Support;

use App\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;

/**
 * Contexto de la organización activa durante la petición.
 * Lo llena el middleware ResolveOrganization a partir de la sesión.
 */
class CurrentOrganization
{
    private ?OrganizationMember $member = null;

    /** @var array<string, bool>|null */
    private ?array $permissions = null;

    public function set(OrganizationMember $member): void
    {
        $this->member = $member;
        $this->permissions = null;
    }

    public function clear(): void
    {
        $this->member = null;
        $this->permissions = null;
    }

    public function id(): ?string
    {
        return $this->member?->organization_id;
    }

    public function member(): ?OrganizationMember
    {
        return $this->member;
    }

    public function has(): bool
    {
        return $this->member !== null;
    }

    /**
     * Verifica un permiso con la forma "<module>.<action>", ej. "pacientes.crear".
     */
    public function can(string $ability): bool
    {
        if (! $this->member || ! $this->member->is_active || ! $this->member->role_id) {
            return false;
        }

        $role = $this->member->role;

        if (! $role || $role->status !== 'active') {
            return false;
        }

        if ($role->is_system_admin) {
            return true;
        }

        if ($this->permissions === null) {
            $this->permissions = DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.organization_id', $this->member->organization_id)
                ->where('role_permissions.role_id', $role->id)
                ->get(['permissions.module', 'permissions.action'])
                ->mapWithKeys(fn ($p) => ["{$p->module}.{$p->action}" => true])
                ->all();
        }

        return isset($this->permissions[$ability]);
    }
}
