<?php

namespace Tests\Feature\Records;

use App\Enums\Dentition;
use App\Enums\ToothCondition;
use App\Models\FluorideApplication;
use App\Models\ToothFinding;

/** RF 6.3 Odontograma y RF 6.6 Aplicaciones de flúor. */
class OdontogramTest extends RecordTestCase
{
    private function findings(int $tooth)
    {
        return ToothFinding::withoutGlobalScopes()->where('patient_id', $this->patient->id)->where('tooth_fdi', $tooth)->orderBy('recorded_at')->get();
    }

    public function test_records_a_finding_and_keeps_the_history(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), ['tooth_fdi' => 26, 'condition' => 'caries', 'surfaces' => ['O', 'M']])
            ->assertSessionHas('toast.title', 'Odontograma actualizado');

        $this->travel(1)->minutes();
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), ['tooth_fdi' => 26, 'condition' => 'restauracion', 'surfaces' => ['O']]);

        $history = $this->findings(26);
        $this->assertCount(2, $history);
        $this->assertNotNull($history[0]->superseded_at);   // el anterior quedó cerrado, no borrado
        $this->assertNull($history[1]->superseded_at);
        $this->assertSame(ToothCondition::Restauracion, $history[1]->condition);
        $this->assertSame(['M', 'O'], $history[0]->surfaces);   // ordenadas

        $this->actingAsMember($this->staff)->get($this->url('odontograma'))->assertSee('Restauración (Oclusal)');
    }

    public function test_validates_fdi_and_condition(): void
    {
        foreach ([19, 49, 56, 10] as $invalid) {
            $this->actingAsMember($this->doctor)->post($this->url('odontograma'), ['tooth_fdi' => $invalid, 'condition' => 'caries'])
                ->assertSessionHasErrors(['tooth_fdi'], null, 'tooth');
        }
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), ['tooth_fdi' => 11, 'condition' => 'fractura'])
            ->assertSessionHasErrors(['condition'], null, 'tooth');

        $this->assertSame(0, ToothFinding::withoutGlobalScopes()->count());
    }

    public function test_temporal_teeth_and_surfaces_only_when_they_apply(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), ['tooth_fdi' => 55, 'condition' => 'ausente', 'surfaces' => ['O']]);

        $finding = $this->findings(55)->sole();
        $this->assertSame(Dentition::Temporal, $finding->dentition);
        $this->assertNull($finding->surfaces);   // una pieza ausente no tiene caras
    }

    public function test_saving_the_same_state_twice_is_rejected(): void
    {
        $data = ['tooth_fdi' => 14, 'condition' => 'ausente'];
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), $data);
        $this->actingAsMember($this->doctor)->post($this->url('odontograma'), $data)->assertSessionHas('toast.type', 'error');

        $this->assertCount(1, $this->findings(14));
    }

    // ── Flúor (RF 6.6) ───────────────────────────────────────────

    public function test_registers_fluoride_applications_grouped_by_year(): void
    {
        $lastYear = today()->subYear()->startOfYear()->addMonths(3);

        $this->actingAsMember($this->doctor)->post($this->url('fluor'), ['applied_on' => $lastYear->toDateString(), 'notes' => 'Barniz'])
            ->assertSessionHas('toast.title', 'Aplicación de flúor registrada');
        $this->actingAsMember($this->doctor)->post($this->url('fluor'), ['applied_on' => today()->toDateString()]);

        $this->assertSame(2, FluorideApplication::withoutGlobalScopes()->count());
        $this->actingAsMember($this->staff)->get($this->url('odontograma'))
            ->assertSee('Control de aplicaciones de flúor')
            ->assertSeeInOrder([(string) today()->year, (string) $lastYear->year])
            // Las observaciones y quién la aplicó se ven en la tarjeta (no solo en un tooltip).
            ->assertSeeInOrder([$this->doctor->displayName(), 'Barniz'], false);
    }

    public function test_fluoride_date_cannot_be_in_the_future(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('fluor'), ['applied_on' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors(['applied_on' => 'La fecha de aplicación no puede ser posterior a hoy.'], null, 'fluoride');
    }

    public function test_deleting_a_fluoride_application_is_logical_and_scoped_to_the_patient(): void
    {
        $this->actingAsMember($this->doctor)->post($this->url('fluor'), ['applied_on' => today()->toDateString()]);
        $application = FluorideApplication::withoutGlobalScopes()->sole();

        // Desde la URL de otro paciente: 404.
        $other = $this->createPatient($this->staff);
        $this->actingAsMember($this->doctor)->delete($this->url("fluor/{$application->id}", $other))->assertNotFound();

        $this->actingAsMember($this->doctor)->delete($this->url("fluor/{$application->id}"))
            ->assertSessionHas('toast.title', 'Aplicación de flúor eliminada');

        $this->assertSoftDeleted('fluoride_applications', ['id' => $application->id]);
        $this->actingAsMember($this->staff)->get($this->url('odontograma'))->assertSee('Sin aplicaciones registradas');
    }
}
