<?php

namespace Tests\Feature\Patients;

use App\Enums\PatientStatus;
use App\Models\AuditLog;
use App\Models\ConsentAcceptance;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientFiscalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return [
            'full_name' => '  ana   maría lópez ',
            'birth_date' => '1990-05-10',
            'sex' => 'female',
            'phone' => '55 1234-5678',
            'email' => 'Ana.Lopez@Correo.MX',
            'consent_signed' => '1',
            'consent_signed_by' => '',
            'consent_method' => 'firma_autografa_escaneada',
            ...$overrides,
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pacientes')->assertRedirect('/login');
    }

    public function test_registers_patient_with_folio_blank_record_and_consent(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('toast.title', 'Paciente registrado exitosamente');

        $patient = Patient::sole();
        $this->assertSame('ANA MARÍA LÓPEZ', $patient->full_name);       // mayúsculas y espacios normalizados
        $this->assertSame('5512345678', $patient->phone);                  // solo dígitos
        $this->assertSame('ana.lopez@correo.mx', $patient->email);
        $this->assertSame('PAC-0001', $patient->folio);
        $this->assertSame(PatientStatus::Active, $patient->status);
        $this->assertSame($member->organization_id, $patient->organization_id);

        $this->assertSame(1, MedicalRecord::where('patient_id', $patient->id)->count());   // expediente 1 a 1
        $consent = ConsentAcceptance::where('patient_id', $patient->id)->sole();
        $this->assertSame('ANA MARÍA LÓPEZ', $consent->accepted_by_name);                 // por defecto, el paciente
        $this->assertSame($member->id, $consent->witness_member_id);
        $this->assertTrue(AuditLog::where('entity_id', $patient->id)->where('action', 'insert')->exists());
    }

    public function test_folios_are_consecutive_per_organization(): void
    {
        $member = $this->member();
        $this->createPatient($member);
        $second = $this->createPatient($member);

        $other = $this->member();
        $otherPatient = $this->createPatient($other);

        $this->assertSame('PAC-0002', $second->folio);
        $this->assertSame('PAC-0001', $otherPatient->folio);
    }

    public function test_registration_requires_signed_consent(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload(['consent_signed' => null]))
            ->assertSessionHasErrors(['consent_signed'], null, 'createPatient');

        $this->assertSame(0, Patient::count());
    }

    public function test_validates_phone_birth_date_and_required_fields(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload(['full_name' => '', 'phone' => '55123', 'birth_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['full_name', 'phone', 'birth_date'], null, 'createPatient');
    }

    public function test_phone_must_be_unique_even_against_inactive_patients(): void
    {
        $member = $this->member();
        $existing = $this->createPatient($member, ['phone' => '5512345678']);
        $existing->update(['status' => PatientStatus::Inactive]);

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload())
            ->assertSessionHasErrors(['phone' => 'El teléfono ya está asignado a otro paciente.'], null, 'createPatient');
    }

    public function test_same_phone_is_allowed_in_another_organization(): void
    {
        $other = $this->member();
        $this->createPatient($other, ['phone' => '5512345678']);

        $member = $this->member();
        $this->actingAsMember($member)->post('/pacientes', $this->validPayload())->assertSessionHasNoErrors();
    }

    public function test_fiscal_data_is_all_or_nothing_and_saved_when_complete(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload(['rfc' => 'LOAA900510AB1']))
            ->assertSessionHasErrors(['legal_name', 'tax_regime_code', 'fiscal_postal_code'], null, 'createPatient');

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload([
                'rfc' => 'loaa900510ab1', 'legal_name' => 'ana maría lópez', 'tax_regime_code' => '605', 'fiscal_postal_code' => '06700',
            ]))
            ->assertSessionHasNoErrors();

        $profile = PatientFiscalProfile::sole();
        $this->assertSame('LOAA900510AB1', $profile->rfc);
        $this->assertSame('ANA MARÍA LÓPEZ', $profile->legal_name);
        $this->assertTrue($profile->is_default);
    }

    public function test_assigned_doctor_must_be_a_doctor_of_the_organization(): void
    {
        $member = $this->member();
        $doctor = $this->member($member->organization, doctor: true);
        $notDoctor = $this->member($member->organization);

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload(['assigned_doctor_member_id' => $notDoctor->id]))
            ->assertSessionHasErrors(['assigned_doctor_member_id'], null, 'createPatient');

        $this->actingAsMember($member)
            ->post('/pacientes', $this->validPayload(['assigned_doctor_member_id' => $doctor->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($doctor->id, Patient::sole()->assigned_doctor_member_id);
    }

    public function test_list_shows_only_active_by_default_and_searches(): void
    {
        $member = $this->member();
        $this->createPatient($member, ['full_name' => 'JUAN ACTIVO', 'phone' => '5511112222']);
        $inactive = $this->createPatient($member, ['full_name' => 'PEDRO INACTIVO']);
        $inactive->update(['status' => PatientStatus::Inactive]);

        $this->actingAsMember($member)->get('/pacientes')
            ->assertOk()->assertSee('JUAN ACTIVO')->assertDontSee('PEDRO INACTIVO');

        $this->actingAsMember($member)->get('/pacientes?estado=todos')
            ->assertSee('JUAN ACTIVO')->assertSee('PEDRO INACTIVO');

        $this->actingAsMember($member)->get('/pacientes?q=55 1111')
            ->assertSee('JUAN ACTIVO');

        $this->actingAsMember($member)->get('/pacientes?q=noexiste')
            ->assertSee('No se encontró ningún paciente');
    }

    public function test_updates_profile_keeping_own_phone(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member, ['phone' => '5512345678']);

        $this->actingAsMember($member)
            ->put("/pacientes/{$patient->id}", $this->validPayload(['full_name' => 'nombre nuevo', 'phone' => '5512345678']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast.title', 'Perfil actualizado correctamente');

        $this->assertSame('NOMBRE NUEVO', $patient->fresh()->full_name);
    }

    public function test_deactivates_and_reactivates_patient(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $this->actingAsMember($member)->patch("/pacientes/{$patient->id}/desactivar")
            ->assertSessionHas('toast.title', 'Paciente desactivado');
        $this->assertSame(PatientStatus::Inactive, $patient->fresh()->status);
        $this->assertNotNull(Patient::find($patient->id), 'La baja es lógica: el registro sigue existiendo.');

        $this->actingAsMember($member)->patch("/pacientes/{$patient->id}/reactivar")
            ->assertSessionHas('toast.title', 'Paciente reactivado');
        $this->assertSame(PatientStatus::Active, $patient->fresh()->status);
    }

    public function test_any_member_of_the_clinic_can_use_the_module(): void
    {
        // Sin roles ni permisos por ahora: cualquier miembro activo puede todo.
        $owner = $this->member();
        $patient = $this->createPatient($owner);
        $other = $this->member($owner->organization);

        $this->actingAsMember($other)->get('/pacientes')->assertOk()->assertSee('Nuevo Paciente');
        $this->actingAsMember($other)->get("/pacientes/{$patient->id}")->assertOk()->assertSee('Editar datos');
        $this->actingAsMember($other)->patch("/pacientes/{$patient->id}/desactivar")->assertSessionHas('toast.title', 'Paciente desactivado');
    }

    public function test_user_without_active_membership_is_logged_out(): void
    {
        $member = $this->member();
        $member->update(['is_active' => false]);

        $this->actingAsMember($member)->get('/pacientes')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_patients_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->createPatient($other, ['full_name' => 'PACIENTE AJENO']);

        $member = $this->member();

        $this->actingAsMember($member)->get('/pacientes?estado=todos')->assertDontSee('PACIENTE AJENO');
        $this->actingAsMember($member)->get("/pacientes/{$foreign->id}")->assertNotFound();
        $this->actingAsMember($member)->patch("/pacientes/{$foreign->id}/desactivar")->assertNotFound();
        $this->assertSame(PatientStatus::Active, Patient::withoutGlobalScopes()->find($foreign->id)->status);
    }

    public function test_exports_csv_with_filters(): void
    {
        $member = $this->member();
        $this->createPatient($member, ['full_name' => 'EXPORTADO UNO']);

        $response = $this->actingAsMember($member)->get('/pacientes/exportar');

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('EXPORTADO UNO', $response->streamedContent());
    }
}
