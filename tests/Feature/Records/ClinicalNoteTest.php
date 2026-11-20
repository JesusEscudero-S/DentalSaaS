<?php

namespace Tests\Feature\Records;

use App\Enums\AppointmentStatus;
use App\Enums\NoteStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** RF 6.4 Registrar diagnóstico / RF 6.5 Modificar diagnóstico: notas, firma y adendas. */
class ClinicalNoteTest extends RecordTestCase
{
    private function note(): ClinicalNote
    {
        return ClinicalNote::withoutGlobalScopes()->latest('created_at')->firstOrFail();
    }

    private function appointmentFor(Patient $patient, AppointmentStatus $status): Appointment
    {
        return Appointment::withoutGlobalScopes()->create([
            'organization_id' => $patient->organization_id,
            'branch_id' => Branch::withoutGlobalScopes()->where('organization_id', $patient->organization_id)->value('id'),
            'patient_id' => $patient->id,
            'doctor_member_id' => $this->doctor->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => $status,
            'notes' => 'Resina',
        ]);
    }

    // ── Registrar ────────────────────────────────────────────────

    public function test_the_doctor_registers_and_signs_a_note(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['action' => 'sign', 'tooth_fdi' => 26]))
            ->assertRedirect($this->url('notas'))
            ->assertSessionHas('toast.title', 'Diagnóstico guardado');   // mensaje del PDF

        $note = $this->note();
        $this->assertSame(NoteStatus::Firmada, $note->status);
        $this->assertSame($this->doctor->id, $note->signed_by_member_id);
        $this->assertSame(26, $note->tooth_fdi);
        $this->assertTrue($note->verifyIntegrity());

        // La nota vive en una consulta del expediente del paciente.
        $this->assertTrue($note->belongsToPatient($this->patient));
        $this->actingAsMember($this->staff)->get($this->url('notas'))->assertSee('Caries interproximal en la pieza 26.');
        $this->actingAsMember($this->staff)->get($this->url('historial'))->assertSee('Caries interproximal en la pieza 26.');
    }

    public function test_note_validations(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['objective' => '']))
            ->assertSessionHasErrors(['objective'], null, 'createNote');

        // PDF 6.4: la fecha no puede ser futura.
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['occurred_on' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['occurred_on' => 'La fecha del diagnóstico no puede ser posterior a hoy.'], null, 'createNote');

        // FDI estricta: 19 no existe.
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['tooth_fdi' => 19]))
            ->assertSessionHasErrors(['tooth_fdi'], null, 'createNote');

        $this->assertSame(0, ClinicalNote::withoutGlobalScopes()->count());
    }

    public function test_only_the_responsible_doctor_can_sign(): void
    {
        // La recepcionista puede escribir un borrador, pero no firmarlo.
        $this->actingAsMember($this->staff)->post($this->url('notas'), $this->noteData(['action' => 'sign']))
            ->assertSessionHasErrors(['doctor_member_id'], null, 'createNote');
        $this->assertSame(0, ClinicalNote::withoutGlobalScopes()->count());

        $this->actingAsMember($this->staff)->post($this->url('notas'), $this->noteData())->assertSessionHasNoErrors();
        $note = $this->note();
        $this->assertSame(NoteStatus::Borrador, $note->status);

        $this->actingAsMember($this->staff)->patch($this->url("notas/{$note->id}/firmar"))->assertSessionHas('toast.type', 'error');
        $this->actingAsMember($this->doctor)->patch($this->url("notas/{$note->id}/firmar"))->assertSessionHas('toast.title', 'Nota firmada');
        $this->assertSame(NoteStatus::Firmada, $note->fresh()->status);
    }

    public function test_a_soap_note_needs_all_four_sections_to_be_signed(): void
    {
        $soap = ['note_type' => 'soap', 'objective' => 'Hallazgos', 'subjective' => 'Dolor'];

        // Como borrador, incompleta está bien.
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData($soap))->assertSessionHasNoErrors();

        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData([...$soap, 'action' => 'sign']))
            ->assertSessionHasErrors(['analysis', 'plan'], null, 'createNote');

        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData([...$soap, 'action' => 'sign', 'analysis' => 'Caries', 'plan' => 'Resina']))
            ->assertSessionHasNoErrors();
        // (las dos notas se crean en el mismo segundo: se busca la firmada en vez de "la última")
        $signed = ClinicalNote::withoutGlobalScopes()->where('status', NoteStatus::Firmada->value)->sole();
        $this->assertTrue($signed->isSoap());
        $this->assertSame('Caries', $signed->analysis);
    }

    // ── Modificar ────────────────────────────────────────────────

    public function test_a_draft_can_be_edited(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData());
        $note = $this->note();

        $this->actingAsMember($this->doctor)->put($this->url("notas/{$note->id}"), [
            'action' => 'draft', 'doctor_member_id' => $this->doctor->id, 'objective' => 'Texto corregido',
        ])->assertSessionHas('toast.title', 'Modificación guardada');   // mensaje del PDF

        $this->assertSame('Texto corregido', $note->fresh()->objective);
    }

    public function test_a_signed_note_is_immutable(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['action' => 'sign']));
        $note = $this->note();

        // La app lo rechaza con un toast…
        $this->actingAsMember($this->doctor)->put($this->url("notas/{$note->id}"), [
            'action' => 'draft', 'doctor_member_id' => $this->doctor->id, 'objective' => 'Cambio',
        ])->assertSessionHas('toast.type', 'error');
        $this->assertSame('Caries interproximal en la pieza 26.', $note->fresh()->objective);

        // …y aunque alguien lo intente directo en la BD, el trigger lo impide (SQLSTATE 45000).
        try {
            DB::table('clinical_notes')->where('id', $note->id)->update(['objective' => 'Cambio']);
            $this->fail('El trigger debió rechazar el UPDATE.');
        } catch (QueryException $e) {
            $this->assertSame('45000', $e->errorInfo[0]);
        }
    }

    public function test_addenda_are_numbered_and_only_for_signed_notes(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData());
        $draft = $this->note();
        $this->actingAsMember($this->doctor)->post($this->url("notas/{$draft->id}/adendas"), ['content' => 'x', 'reason' => 'y'])
            ->assertSessionHas('toast.type', 'error');

        $this->actingAsMember($this->doctor)->patch($this->url("notas/{$draft->id}/firmar"));

        $this->actingAsMember($this->doctor)->post($this->url("notas/{$draft->id}/adendas"), ['content' => '', 'reason' => ''])
            ->assertSessionHasErrors(['content', 'reason'], null, 'addendum');

        $this->actingAsMember($this->staff)->post($this->url("notas/{$draft->id}/adendas"), ['content' => 'Era la 27', 'reason' => 'Error de pieza'])
            ->assertSessionHas('toast.title', 'Adenda agregada');
        $this->actingAsMember($this->doctor)->post($this->url("notas/{$draft->id}/adendas"), ['content' => 'Confirmado', 'reason' => 'Revisión']);

        $this->assertSame([1, 2], $draft->addenda()->withoutGlobalScopes()->pluck('version_number')->all());
        $this->assertTrue($draft->fresh()->verifyIntegrity());   // la nota original no cambió
        $this->actingAsMember($this->staff)->get($this->url("notas/{$draft->id}"))->assertSee('Era la 27')->assertSee('Versión 2');
    }

    // ── Cita, consulta y aislamiento ────────────────────────────

    public function test_notes_linked_to_the_same_appointment_share_the_encounter(): void
    {
        $appointment = $this->appointmentFor($this->patient, AppointmentStatus::Completada);

        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['appointment_id' => $appointment->id]))->assertSessionHasNoErrors();
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['appointment_id' => $appointment->id, 'objective' => 'Segunda nota']));

        $this->assertSame(1, ClinicalEncounter::withoutGlobalScopes()->count());
        $this->assertSame($appointment->id, ClinicalEncounter::withoutGlobalScopes()->value('appointment_id'));

        // "Abrir expediente" desde la Agenda llega a la nota SOAP con la cita ya elegida.
        $this->actingAsMember($this->staff)->get($this->url('notas/nueva').'?cita='.$appointment->id)
            ->assertOk()->assertSee('Cita relacionada: Resina');
    }

    public function test_cannot_link_an_appointment_of_another_patient_or_not_started(): void
    {
        $other = $this->createPatient($this->staff);
        $foreign = $this->appointmentFor($other, AppointmentStatus::Completada);
        $pending = $this->appointmentFor($this->patient, AppointmentStatus::Pendiente);

        foreach ([$foreign, $pending] as $appointment) {
            $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData(['appointment_id' => $appointment->id]))
                ->assertSessionHasErrors(['appointment_id'], null, 'createNote');
        }
    }

    public function test_a_note_cannot_be_opened_from_another_patient_url(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('notas'), $this->noteData());
        $note = $this->note();
        $other = $this->createPatient($this->staff);

        $this->actingAsMember($this->staff)->get($this->url("notas/{$note->id}", $other))->assertNotFound();
        $this->actingAsMember($this->staff)->get($this->url("notas/{$note->id}"))->assertOk();
    }
}
