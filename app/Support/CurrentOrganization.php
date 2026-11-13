<?php

namespace App\Support;

use App\Models\OrganizationMember;

/**
 * Clínica (organización) activa durante la petición y el miembro que la usa.
 * La llena el middleware ResolveOrganization después del login.
 *
 * Los modelos con el trait BelongsToOrganization filtran sus consultas por esta
 * organización y la asignan al crear registros.
 */
class CurrentOrganization
{
    private ?OrganizationMember $member = null;

    public function set(OrganizationMember $member): void
    {
        $this->member = $member;
    }

    public function clear(): void
    {
        $this->member = null;
    }

    /** UUID de la clínica activa. */
    public function id(): ?string
    {
        return $this->member?->organization_id;
    }

    /** Miembro (usuario dentro de la clínica) que hace la petición. */
    public function member(): ?OrganizationMember
    {
        return $this->member;
    }

    public function has(): bool
    {
        return $this->member !== null;
    }
}
