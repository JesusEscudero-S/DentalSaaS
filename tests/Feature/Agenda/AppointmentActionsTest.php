<?php

namespace Tests\Feature\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use Illuminate\Support\Carbon;

/** RF 4.1, 4.3, 4.4 y RN-C1, C3, C4, C5: crear, reagendar, cancelar e iniciar/finalizar citas. */
class AppointmentActionsTest extends AgendaTestCase
{
    private function history(Appointment $appointment)
    {
        return AppointmentStatusHistory::withoutGlobalScopes()->where('appointment_id', $appointment->id)->orderBy('created_at')->get();
    }

    // ── Crear (RF 4.1) ──────────────────────────────────────────

    public function test_creates_an_appointment_with_history(): void
    {
        $data = $this->newAppointmentData();

        $this->actingAsMember($this->staff)->post('/agenda/citas', $data)
            ->assertRedirect('/agenda?semana='.$data['date'])
            ->assertSessionHas('toast.title', 'Cita programada exitosamente');

        $appointment = Appointment::withoutGlobalScopes()->sole();
        $this->assertSame(AppointmentStatus::Pendiente, $appointment->status);
        $this->assertSame('11:00', $appointment->ends_at->format('H:i'));
        $this->assertSame($this->staff->user_id, $appointment->created_by);

        $history = $this->history($appointment)->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(AppointmentStatus::Pendiente, $history->to_status);
    }

    public function test_requires_all_fields(): void
    {
        $this->actingAsMember($this->staff)->post('/agenda/citas', [])
            ->assertSessionHasErrors(['patient_id', 'doctor_member_id', 'date', 'start_time', 'duration_minutes', 'notes'], null, 'createAppointment');
    }

    public function test_cannot_schedule_in_the_past_or_outside_hours(): void
    {
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors(['date' => 'No se pueden agendar citas en fechas pasadas.'], null, 'createAppointment');

        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '19:30']))
            ->assertSessionHasErrors(['start_time' => 'La cita debe quedar entre 8:00 y 20:00.'], null, 'createAppointment');

        $this->assertSame(0, Appointment::withoutGlobalScopes()->count());
    }

    public function test_doctor_must_be_a_doctor_and_patient_must_be_active(): void
    {
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['doctor_member_id' => $this->staff->id]))
            ->assertSessionHasErrors(['doctor_member_id'], null, 'createAppointment');

        $this->patient->update(['status' => 'inactive']);
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData())
            ->assertSessionHasErrors(['patient_id'], null, 'createAppointment');
    }

    // ── RN-C1: cero empalmes ────────────────────────────────────

    public function test_rejects_overlapping_appointments_for_the_same_doctor(): void
    {
        $this->appointment(Carbon::parse($this->newAppointmentData()['date'].' 10:00'));

        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '10:30']))
            ->assertSessionHasErrors(['start_time' => 'Horario no disponible para el odontólogo seleccionado.'], null, 'createAppointment');
    }

    public function test_allows_back_to_back_and_other_doctors_and_cancelled_slots(): void
    {
        $date = $this->newAppointmentData()['date'];
        $this->appointment(Carbon::parse("$date 10:00"));                                          // 10:00–11:00
        $this->appointment(Carbon::parse("$date 12:00"), status: AppointmentStatus::Cancelada);  // cancelada: no ocupa

        // Justo cuando termina la otra
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '11:00']))->assertSessionHasNoErrors();
        // Sobre una cancelada
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '12:00']))->assertSessionHasNoErrors();
        // Otro doctor a la misma hora (con otro paciente: el mismo paciente no puede estar en dos citas a la vez)
        $other = $this->member($this->staff->organization, doctor: true);
        $otherPatient = $this->createPatient($this->staff);
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '10:00', 'doctor_member_id' => $other->id, 'patient_id' => $otherPatient->id]))->assertSessionHasNoErrors();
    }

    public function test_a_patient_cannot_have_two_appointments_at_the_same_time(): void
    {
        $date = $this->newAppointmentData()['date'];
        $this->appointment(Carbon::parse("$date 10:00"));   // 10:00–11:00 con $this->doctor
        $other = $this->member($this->staff->organization, doctor: true);

        // Mismo paciente, otro doctor, se cruza a las 10:30 → rechazada.
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '10:30', 'doctor_member_id' => $other->id]))
            ->assertSessionHasErrors(['start_time' => 'El paciente ya tiene una cita de 10:00 a 11:00 en ese horario.'], null, 'createAppointment');

        // Otro paciente con ese doctor a la misma hora → sí se puede.
        $otherPatient = $this->createPatient($this->staff);
        $this->actingAsMember($this->staff)->post('/agenda/citas', $this->newAppointmentData(['start_time' => '10:30', 'doctor_member_id' => $other->id, 'patient_id' => $otherPatient->id]))
            ->assertSessionHasNoErrors();

        // Al reagendar también se revisa: mover la cita del otro paciente encima no choca, pero la de $this->patient sí.
        $second = $this->appointment(Carbon::parse("$date 15:00"), doctor: $other);
        $this->actingAsMember($this->staff)->put("/agenda/citas/{$second->id}/reagendar", [
            'doctor_member_id' => $other->id, 'date' => $date, 'start_time' => '09:30', 'duration_minutes' => 60,
        ])->assertSessionHasErrors(['start_time'], null, 'rescheduleAppointment');
    }

    // ── Reagendar (RF 4.3) ──────────────────────────────────────

    public function test_reschedules_and_records_old_and_new_dates(): void
    {
        $appointment = $this->appointment(now()->addDays(2)->setTime(10, 0));
        $newDate = now()->addDays(3)->toDateString();

        $this->actingAsMember($this->staff)->put("/agenda/citas/{$appointment->id}/reagendar", [
            'doctor_member_id' => $this->doctor->id, 'date' => $newDate, 'start_time' => '16:00', 'duration_minutes' => 30,
        ])->assertSessionHas('toast.title', 'Cita actualizada correctamente');

        $appointment->refresh();
        $this->assertSame($newDate.' 16:00', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame(30, $appointment->durationMinutes());

        $history = $this->history($appointment)->last();
        $this->assertSame('10:00', $history->old_starts_at->format('H:i'));
        $this->assertSame('16:00', $history->new_starts_at->format('H:i'));
    }

    public function test_reschedule_checks_overlaps_but_not_against_itself(): void
    {
        $start = now()->addDays(2)->setTime(10, 0);
        $appointment = $this->appointment($start);
        $this->appointment($start->copy()->setTime(12, 0));
        $payload = ['doctor_member_id' => $this->doctor->id, 'date' => $start->toDateString(), 'duration_minutes' => 60];

        // Moverla 30 min dentro de su propio horario: no choca consigo misma.
        $this->actingAsMember($this->staff)->put("/agenda/citas/{$appointment->id}/reagendar", [...$payload, 'start_time' => '10:30'])
            ->assertSessionHasNoErrors();

        // Pero sí contra la de las 12:00.
        $this->actingAsMember($this->staff)->put("/agenda/citas/{$appointment->id}/reagendar", [...$payload, 'start_time' => '11:30'])
            ->assertSessionHasErrors(['start_time'], null, 'rescheduleAppointment');
    }

    // ── Cancelar (RF 4.4 / RN-C3) ───────────────────────────────

    public function test_cancel_requires_reason_and_keeps_the_appointment(): void
    {
        $appointment = $this->appointment(now()->addDay()->setTime(10, 0));

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/cancelar", ['reason' => ''])
            ->assertSessionHasErrors(['reason'], null, 'cancelAppointment');

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/cancelar", ['reason' => 'Se enfermó'])
            ->assertSessionHas('toast.title', 'Estatus de cita cambiado a Cancelada');

        $this->assertSame(AppointmentStatus::Cancelada, $appointment->fresh()->status);   // baja lógica: sigue existiendo
        $this->assertSame('Se enfermó', $this->history($appointment)->last()->reason);
    }

    // ── Confirmar ───────────────────────────────────────────────

    public function test_confirms_a_pending_appointment_only_once(): void
    {
        $appointment = $this->appointment(now()->addDay()->setTime(10, 0));

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/confirmar")
            ->assertSessionHas('toast.title', 'Cita confirmada');
        $this->assertSame(AppointmentStatus::Confirmada, $appointment->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmada, $this->history($appointment)->last()->to_status);

        // Ya confirmada: confirmar otra vez no está permitido (pero iniciar sí).
        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/confirmar")
            ->assertSessionHas('toast.type', 'error');
        $this->assertTrue(AppointmentStatus::Confirmada->canTransitionTo(AppointmentStatus::EnCurso));
    }

    // ── Iniciar / Finalizar y máquina de estados ────────────────

    public function test_start_and_finish_follow_the_state_machine(): void
    {
        $appointment = $this->appointment(now()->addHour());

        // No se puede finalizar algo que no ha empezado.
        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/finalizar")
            ->assertSessionHas('toast.type', 'error');
        $this->assertSame(AppointmentStatus::Pendiente, $appointment->fresh()->status);

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/iniciar")->assertSessionHas('toast.title', 'Cita iniciada');
        $this->assertSame(AppointmentStatus::EnCurso, $appointment->fresh()->status);

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/finalizar")->assertSessionHas('toast.title', 'Cita finalizada');
        $this->assertSame(AppointmentStatus::Completada, $appointment->fresh()->status);

        // Una cita completada ya no se cancela ni se reagenda.
        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$appointment->id}/cancelar", ['reason' => 'x'])->assertSessionHas('toast.type', 'error');
        $this->assertSame(AppointmentStatus::Completada, $appointment->fresh()->status);

        $this->assertSame(
            [null, 'en_curso', 'completada'],
            [null, ...$this->history($appointment)->pluck('to_status')->map->value->all()],
        );
    }

    // ── RN-C5: retrasos y "No asistió" ──────────────────────────

    public function test_no_show_only_after_thirty_minutes_late(): void
    {
        $onTime = $this->appointment(now()->subMinutes(10));
        $late = $this->appointment(now()->subMinutes(45), 15);

        $this->assertFalse($onTime->isLate());
        $this->assertTrue($late->isLate());

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$onTime->id}/no-asistio")->assertSessionHas('toast.type', 'error');
        $this->assertSame(AppointmentStatus::Pendiente, $onTime->fresh()->status);

        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$late->id}/no-asistio")->assertSessionHas('toast.title', 'Cita marcada como No asistió');
        $this->assertSame(AppointmentStatus::NoAsistio, $late->fresh()->status);
    }

    public function test_late_appointment_is_highlighted_in_the_calendar(): void
    {
        $this->appointment(now()->subMinutes(45), 15);

        $this->actingAsMember($this->staff)->get('/agenda?vista=dia&fecha='.now()->toDateString())->assertSee('Retraso');
    }
}
