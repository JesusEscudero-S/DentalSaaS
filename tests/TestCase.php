<?php

namespace Tests;

use App\Enums\ConsentMethod;
use App\Models\DoctorProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\User;
use App\Services\Patients\PatientService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Crea un miembro (usuario dentro de una clínica). Si no se pasa clínica, crea una nueva.
     */
    protected function member(?Organization $organization = null, bool $doctor = false): OrganizationMember
    {
        $organization ??= Organization::create(['commercial_name' => 'Clínica '.Str::random(6)]);

        $member = OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        if ($doctor) {
            DoctorProfile::create(['member_id' => $member->id, 'organization_id' => $organization->id, 'professional_license' => '1234567']);
        }

        return $member->load('user', 'organization');
    }

    /**
     * Inicia sesión como el miembro. El contexto se limpia para que la petición
     * dependa solo del middleware ResolveOrganization (como en la app real).
     */
    protected function actingAsMember(OrganizationMember $member): static
    {
        app(CurrentOrganization::class)->clear();

        return $this->actingAs($member->user);
    }

    /** Registra un paciente en la clínica del miembro usando el servicio real. */
    protected function createPatient(OrganizationMember $member, array $overrides = []): Patient
    {
        app(CurrentOrganization::class)->set($member);

        return app(PatientService::class)->create([
            'full_name' => 'PACIENTE '.Str::upper(Str::random(5)),
            'birth_date' => '1990-01-01',
            'sex' => 'unspecified',
            'phone' => (string) random_int(5500000000, 5599999999),
            'email' => null,
            'assigned_doctor_member_id' => null,
            'consent_signed_by' => 'PACIENTE',
            'consent_method' => ConsentMethod::ScannedSignature->value,
            ...$overrides,
        ]);
    }
}
