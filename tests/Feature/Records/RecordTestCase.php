<?php

namespace Tests\Feature\Records;

use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base de las pruebas del Expediente: una clínica con sucursal, una recepcionista ($staff),
 * una doctora ($doctor) y un paciente ($patient, con su expediente en blanco).
 */
abstract class RecordTestCase extends TestCase
{
    use RefreshDatabase;

    protected OrganizationMember $staff;

    protected OrganizationMember $doctor;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->member();
        $this->doctor = $this->member($this->staff->organization, doctor: true);
        Branch::create(['organization_id' => $this->staff->organization_id, 'name' => 'Clínica Centro', 'is_main' => true]);
        $this->patient = $this->createPatient($this->staff, ['full_name' => 'PACIENTE DE PRUEBA']);
    }

    /** URL de una pestaña o acción del expediente: url('notas') → /pacientes/{id}/notas */
    protected function url(string $path = '', ?Patient $patient = null): string
    {
        return '/pacientes/'.($patient ?? $this->patient)->id.($path ? '/'.$path : '');
    }

    /** Datos válidos de una nota simple. */
    protected function noteData(array $overrides = []): array
    {
        return [
            'note_type' => 'simple',
            'action' => 'draft',
            'doctor_member_id' => $this->doctor->id,
            'occurred_on' => today()->toDateString(),
            'objective' => 'Caries interproximal en la pieza 26.',
            ...$overrides,
        ];
    }
}
