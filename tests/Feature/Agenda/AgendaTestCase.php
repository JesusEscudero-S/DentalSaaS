<?php

namespace Tests\Feature\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Base de las pruebas de la agenda: prepara una clínica con su sucursal,
 * una recepcionista ($staff), un doctor ($doctor) y un paciente ($patient).
 */
abstract class AgendaTestCase extends TestCase
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

    /** Crea una cita directo en la BD (sin pasar por el servicio). */
    protected function appointment(Carbon $start, int $minutes = 60, AppointmentStatus $status = AppointmentStatus::Pendiente, ?OrganizationMember $doctor = null, ?Patient $patient = null): Appointment
    {
        $doctor ??= $this->doctor;
        $patient ??= $this->patient;

        return Appointment::withoutGlobalScopes()->create([
            'organization_id' => $doctor->organization_id,
            'branch_id' => Branch::withoutGlobalScopes()->where('organization_id', $doctor->organization_id)->value('id'),
            'patient_id' => $patient->id,
            'doctor_member_id' => $doctor->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
            'status' => $status,
            'notes' => 'Limpieza',
        ]);
    }

    /** Datos válidos para el formulario "Nueva cita" (pasado mañana a las 10:00). */
    protected function newAppointmentData(array $overrides = []): array
    {
        return [
            'patient_id' => $this->patient->id,
            'doctor_member_id' => $this->doctor->id,
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'notes' => 'Limpieza',
            ...$overrides,
        ];
    }
}
