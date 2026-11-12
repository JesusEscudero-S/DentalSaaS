<?php

namespace Tests;

use App\Enums\ConsentMethod;
use App\Models\DoctorProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Patients\PatientService;
use App\Support\CurrentOrganization;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Crea una organización con un miembro del rol indicado y su contexto activo.
     *
     * @param  array<int, string>  $patientActions  permisos del módulo pacientes
     */
    protected function memberWith(array $patientActions = [], bool $isAdmin = false, ?Organization $organization = null, bool $doctor = false): OrganizationMember
    {
        if (Permission::count() === 0) {
            $this->seed(PermissionSeeder::class);
        }

        $organization ??= Organization::create(['commercial_name' => 'Clínica '.Str::random(6)]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'code' => 'rol_'.Str::random(6),
            'name' => 'Rol '.Str::random(6),
            'is_system_admin' => $isAdmin,
        ]);

        foreach (Permission::where('module', 'pacientes')->whereIn('action', $patientActions)->get() as $permission) {
            DB::table('role_permissions')->insert([
                'organization_id' => $organization->id,
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }

        $member = OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'role_id' => $role->id,
        ]);

        if ($doctor) {
            DoctorProfile::create(['member_id' => $member->id, 'organization_id' => $organization->id, 'professional_license' => '1234567']);
        }

        return $member->load('user', 'role');
    }

    /**
     * Inicia sesión como el miembro y guarda su organización en sesión (como lo hace el login).
     * El contexto se limpia para que la petición dependa solo del middleware ResolveOrganization.
     */
    protected function actingAsMember(OrganizationMember $member): static
    {
        app(CurrentOrganization::class)->clear();

        return $this->actingAs($member->user)->withSession(['organization_id' => $member->organization_id]);
    }

    /** Registra un paciente en la organización del miembro usando el servicio real. */
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
