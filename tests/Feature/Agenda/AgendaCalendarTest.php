<?php

namespace Tests\Feature\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Services\Agenda\AgendaService;

/** RF 4.2 Consultar agenda: semana, día, filtro por doctor, canceladas y aislamiento. */
class AgendaCalendarTest extends AgendaTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/agenda')->assertRedirect('/login');
    }

    public function test_week_view_shows_the_appointments_of_the_week(): void
    {
        $this->appointment(now()->startOfWeek()->addDay()->setTime(10, 0));
        $this->appointment(now()->startOfWeek()->addWeek()->setTime(10, 0)); // la semana siguiente

        $this->actingAsMember($this->staff)->get('/agenda')
            ->assertOk()
            ->assertSee('PACIENTE DE PRUEBA')
            ->assertSee('Semana del '.now()->startOfWeek()->format('j'));

        // Solo una tarjeta: la de la semana siguiente no aparece.
        $this->assertSame(1, substr_count(
            $this->actingAsMember($this->staff)->get('/agenda')->getContent(),
            'Ver cita de PACIENTE DE PRUEBA',
        ));
    }

    public function test_can_navigate_to_another_week(): void
    {
        $nextMonday = now()->startOfWeek()->addWeek();
        $this->appointment($nextMonday->copy()->setTime(9, 0));

        $this->actingAsMember($this->staff)->get('/agenda?semana='.$nextMonday->toDateString())
            ->assertOk()
            ->assertSee('Ver cita de PACIENTE DE PRUEBA', false);
    }

    public function test_cancelled_appointments_free_their_slot(): void
    {
        $this->appointment(now()->startOfWeek()->setTime(10, 0), status: AppointmentStatus::Cancelada);

        $this->actingAsMember($this->staff)->get('/agenda')->assertDontSee('Ver cita de PACIENTE DE PRUEBA', false);
    }

    public function test_doctor_filter_shows_only_that_doctor(): void
    {
        $other = $this->member($this->staff->organization, doctor: true);
        $this->appointment(now()->startOfWeek()->setTime(9, 0));
        $this->appointment(now()->startOfWeek()->setTime(11, 0), doctor: $other);

        $html = $this->actingAsMember($this->staff)->get('/agenda?doctor='.$other->id)->assertOk()->getContent();

        $this->assertStringContainsString('a las 11:00', $html);
        $this->assertStringNotContainsString('a las 09:00', $html);
    }

    public function test_day_view_shows_a_single_day(): void
    {
        $day = now()->startOfWeek()->addDays(2);
        $this->appointment($day->copy()->setTime(9, 0));
        $this->appointment($day->copy()->addDay()->setTime(9, 0));

        $html = $this->actingAsMember($this->staff)
            ->get('/agenda?vista=dia&fecha='.$day->toDateString())
            ->assertOk()
            ->assertSee(ucfirst($day->translatedFormat('l j \d\e F Y')))
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Ver cita de PACIENTE DE PRUEBA'));
    }

    public function test_month_view_shows_the_whole_month_with_counts(): void
    {
        $month = now()->addMonthNoOverflow()->startOfMonth();
        $day = $month->copy()->addDays(9);                      // el día 10 del mes
        $this->appointment($day->copy()->setTime(9, 0));
        $this->appointment($day->copy()->setTime(11, 0));
        $this->appointment($month->copy()->addMonthNoOverflow()->addDays(14)->setTime(9, 0));   // otro mes: no aparece

        $html = $this->actingAsMember($this->staff)
            ->get('/agenda?vista=mes&mes='.$month->format('Y-m'))
            ->assertOk()
            ->assertSee(ucfirst($month->translatedFormat('F \d\e Y')))
            ->assertSee('2 citas')
            ->assertSee('vista=dia&amp;fecha='.$day->toDateString(), false)   // el día lleva a la vista Día
            ->getContent();

        $this->assertSame(2, substr_count($html, 'Ver cita de PACIENTE DE PRUEBA'));
    }

    public function test_month_view_shows_only_three_appointments_per_day(): void
    {
        $day = now()->addDays(2)->startOfDay();
        foreach ([9, 10, 11, 12, 13] as $hour) {
            $this->appointment($day->copy()->setTime($hour, 0), 30);
        }

        $this->actingAsMember($this->staff)->get('/agenda?vista=mes&mes='.$day->format('Y-m'))
            ->assertSee('5 citas')
            ->assertSee('+2 más');
    }

    public function test_invalid_url_parameters_do_not_break_the_page(): void
    {
        $this->actingAsMember($this->staff)->from('/agenda')->get('/agenda?semana=hola&doctor=123&mes=2026-13')
            ->assertRedirect('/agenda');
    }

    public function test_appointments_of_another_clinic_are_not_visible(): void
    {
        $otherStaff = $this->member();
        $otherDoctor = $this->member($otherStaff->organization, doctor: true);
        Branch::create(['organization_id' => $otherStaff->organization_id, 'name' => 'Otra', 'is_main' => true]);
        // El paciente también debe ser de la otra clínica: la FK compuesta (organization_id, patient_id) lo exige.
        $foreignPatient = $this->createPatient($otherStaff);
        $foreign = $this->appointment(now()->addDay()->setTime(10, 0), doctor: $otherDoctor, patient: $foreignPatient);

        $this->actingAsMember($this->staff)->get('/agenda?semana='.now()->addDay()->toDateString())
            ->assertDontSee('a las 10:00', false);

        // Y tampoco se puede operar sobre ella: route model binding + filtro de clínica = 404.
        $this->actingAsMember($this->staff)->patch("/agenda/citas/{$foreign->id}/iniciar")->assertNotFound();
        $this->assertSame(AppointmentStatus::Pendiente, Appointment::withoutGlobalScopes()->find($foreign->id)->status);
    }

    public function test_new_appointment_modal_has_a_patient_search_with_only_active_patients(): void
    {
        $this->createPatient($this->staff, ['full_name' => 'PACIENTE INACTIVO'])->update(['status' => 'inactive']);

        $this->actingAsMember($this->staff)->get('/agenda')
            ->assertSee('role="combobox"', false)
            ->assertSee('Busca un paciente por nombre o folio')
            ->assertSee('PACIENTE DE PRUEBA')
            ->assertDontSee('PACIENTE INACTIVO');
    }

    public function test_card_position_matches_its_time_and_duration(): void
    {
        $appointment = $this->appointment(now()->addDay()->setTime(9, 30), 90);

        // 9:30 → 90 min después de las 8:00; 60 px por hora = 1 px por minuto.
        $this->assertSame(['top' => 90.0, 'height' => 90.0], AgendaService::position($appointment));
    }
}
