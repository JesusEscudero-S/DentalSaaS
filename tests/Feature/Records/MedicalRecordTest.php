<?php

namespace Tests\Feature\Records;

use App\Models\AuditLog;
use App\Models\MedicalRecord;

/** RF 6.1 Consultar expediente, RF 6.2 Antecedentes, RN-E1 y RN-E3. */
class MedicalRecordTest extends RecordTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url('notas'))->assertRedirect('/login');
    }

    public function test_every_tab_of_the_record_loads(): void
    {
        foreach (['', 'historial', 'notas', 'notas/nueva', 'archivos', 'odontograma', 'odontograma?denticion=temporal'] as $tab) {
            $this->actingAsMember($this->staff)->get($this->url($tab))
                ->assertOk()
                ->assertSee('PACIENTE DE PRUEBA');
        }
    }

    public function test_a_patient_has_exactly_one_record(): void
    {
        // RN-E1: se crea al registrar al paciente y visitar las pestañas no crea otro.
        $this->actingAsMember($this->staff)->get($this->url('historial'))->assertOk();
        $this->actingAsMember($this->staff)->get($this->url('notas'))->assertOk();

        $this->assertSame(1, MedicalRecord::withoutGlobalScopes()->where('patient_id', $this->patient->id)->count());
    }

    public function test_updates_the_medical_history_and_shows_the_red_alert(): void
    {
        $this->actingAsMember($this->staff)->get($this->url())->assertDontSee('Alerta clínica');

        $this->actingAsMember($this->doctor)->put($this->url('antecedentes'), [
            'allergies' => 'Penicilina',
            'medical_history' => 'Diabetes tipo 2',
        ])->assertSessionHas('toast.title', 'Antecedentes actualizados');

        $record = MedicalRecord::withoutGlobalScopes()->where('patient_id', $this->patient->id)->sole();
        $this->assertSame('Penicilina', $record->allergies);

        // RN-E3: la alerta aparece en todas las pestañas.
        foreach (['', 'notas', 'odontograma'] as $tab) {
            $this->actingAsMember($this->staff)->get($this->url($tab))->assertSee('Alerta clínica')->assertSee('Penicilina');
        }

        // En la bitácora van los nombres de los campos, no el texto clínico.
        $log = AuditLog::withoutGlobalScopes()->where('entity_type', 'medical_records')->latest('created_at')->first();
        $this->assertSame(['fields' => ['allergies', 'medical_history']], $log->changed_fields);
    }

    public function test_medical_history_fields_can_be_emptied(): void
    {
        $this->actingAsMember($this->doctor)->put($this->url('antecedentes'), ['allergies' => 'Látex']);
        $this->actingAsMember($this->doctor)->put($this->url('antecedentes'), ['allergies' => '', 'medical_history' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(MedicalRecord::withoutGlobalScopes()->where('patient_id', $this->patient->id)->value('allergies'));
        $this->actingAsMember($this->staff)->get($this->url())->assertDontSee('Alerta clínica');
    }

    public function test_records_of_another_clinic_are_not_accessible(): void
    {
        $otherStaff = $this->member();
        $foreign = $this->createPatient($otherStaff);

        foreach (['historial', 'notas', 'archivos', 'odontograma'] as $tab) {
            $this->actingAsMember($this->staff)->get($this->url($tab, $foreign))->assertNotFound();
        }
        $this->actingAsMember($this->staff)->put($this->url('antecedentes', $foreign), ['allergies' => 'x'])->assertNotFound();
    }
}
