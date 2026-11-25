<?php

namespace Tests\Feature\Treatments;

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Quote;
use App\Models\Treatment;
use App\Services\Treatments\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Módulo 7. Gestión de tratamientos (solo con tablas existentes). */
class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMember $doctor;

    private Patient $patient;

    private Treatment $resin;

    private Treatment $cleaning;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = $this->member(doctor: true);
        $org = $this->doctor->organization_id;
        Branch::create(['organization_id' => $org, 'name' => 'Centro', 'is_main' => true]);
        $this->resin = Treatment::create(['organization_id' => $org, 'name' => 'Resina', 'duration_minutes' => 45, 'price' => 950]);
        $this->cleaning = Treatment::create(['organization_id' => $org, 'name' => 'Limpieza dental', 'duration_minutes' => 45, 'price' => 800]);
        $this->patient = $this->createPatient($this->doctor);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'doctor_member_id' => $this->doctor->id,
            'diagnosis' => 'Caries en 36 y gingivitis.',
            'diagnosed_on' => now()->toDateString(),
            'procedures' => [
                ['treatment_id' => $this->resin->id, 'tooth_fdi' => '36', 'unit_price' => '950', 'notes' => 'oclusal'],
                ['treatment_id' => $this->cleaning->id, 'tooth_fdi' => '', 'unit_price' => '800.00', 'notes' => ''],
            ],
            ...$overrides,
        ];
    }

    private function createPlan(array $overrides = []): Quote
    {
        $this->actingAsMember($this->doctor)
            ->post(route('records.treatments.store', $this->patient), $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return Quote::latest('created_at')->firstOrFail();
    }

    private function authorizePlan(Quote $quote): void
    {
        $this->patch(route('records.treatments.authorize', [$this->patient, $quote]), [
            'patient_agrees' => '1', 'accepted_by_name' => 'paciente prueba', 'method' => 'firma_autografa_escaneada',
        ])->assertSessionHas('toast.type', 'success');
    }

    private function recordSession(Quote $quote, array $procedureIds, array $overrides = [])
    {
        return $this->post(route('records.treatments.sessions.store', [$this->patient, $quote]), [
            'performed_on' => now()->toDateString(),
            'activities' => 'Se realizó el procedimiento.',
            'doctor_member_id' => $this->doctor->id,
            'procedures' => $procedureIds,
            ...$overrides,
        ]);
    }

    private function plan(Quote $quote)
    {
        return app(TreatmentPlanService::class)->plan($quote->fresh());
    }

    public function test_no_agrega_tablas_ni_columnas(): void
    {
        foreach (['treatment_plans', 'treatment_plan_versions', 'treatment_sessions', 'treatment_session_items', 'treatment_session_notes'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertFalse(Schema::hasColumn('users', 'phone'));
        $this->assertFalse(Schema::hasColumn('users', 'must_change_password'));
    }

    public function test_crea_plan_propuesto_con_presupuesto_y_nota_de_diagnostico(): void
    {
        $quote = $this->createPlan();
        $plan = $this->plan($quote);

        $this->assertSame('propuesto', $plan->status->value);
        $this->assertSame('enviado', $quote->status);
        $this->assertStringStartsWith('PRE-', $quote->folio);
        $this->assertEquals(1750.00, (float) $quote->total);
        $this->assertSame('Caries en 36 y gingivitis.', $plan->diagnosis);
        $this->assertSame('borrador', $plan->diagnosisNote->status->value);

        $this->assertCount(2, $plan->procedures);
        $this->assertSame(36, $plan->procedures[0]->tooth_fdi);
        $this->assertSame('Resina — oclusal', $plan->procedures[0]->quoteItem->description_snapshot);
        $this->assertTrue($plan->procedures->every(fn ($pt) => $pt->status === 'planeado'));

        $this->get(route('records.treatments.index', $this->patient))->assertOk()->assertSee($quote->folio);
        $this->get(route('records.treatments.show', [$this->patient, $quote]))->assertOk()->assertSee('Caries en 36');
        $this->get(route('records.notes.index', $this->patient))->assertOk()->assertSee($quote->folio); // también en Notas
    }

    public function test_validaciones_del_plan(): void
    {
        $this->actingAsMember($this->doctor)
            ->post(route('records.treatments.store', $this->patient), $this->payload([
                'diagnosed_on' => now()->addDay()->toDateString(),
                'procedures' => [['treatment_id' => $this->resin->id, 'tooth_fdi' => '19', 'unit_price' => '-5']],
            ]))
            ->assertSessionHasErrorsIn('treatmentPlan', ['diagnosed_on', 'procedures.0.tooth_fdi', 'procedures.0.unit_price']);

        $this->post(route('records.treatments.store', $this->patient), $this->payload(['procedures' => []]))
            ->assertSessionHasErrorsIn('treatmentPlan', ['procedures']);

        $this->assertSame(0, Quote::count());
    }

    public function test_no_registra_sesiones_sin_autorizacion(): void
    {
        $quote = $this->createPlan();

        $this->recordSession($quote, [])->assertSessionHas('toast.type', 'error');
        $this->assertSame(1, ClinicalNote::count()); // solo la del diagnóstico
    }

    public function test_flujo_completo_autorizar_sesiones_y_concluir(): void
    {
        $quote = $this->createPlan();
        $this->authorizePlan($quote);

        $plan = $this->plan($quote);
        $this->assertSame('autorizado', $plan->status->value);
        $this->assertSame('aprobado', $quote->fresh()->status);
        $this->assertNotNull($plan->consent);

        [$resin, $cleaning] = $plan->procedures->all();

        $this->recordSession($quote, [$resin->id])->assertSessionHas('toast.title', 'Sesión registrada');
        $this->assertSame('en_proceso', $this->plan($quote)->status->value);
        $this->assertSame('realizado', $resin->fresh()->status);

        $this->recordSession($quote, [$cleaning->id])->assertSessionHas('toast.title', 'Tratamiento concluido');
        $plan = $this->plan($quote);
        $this->assertSame('concluido', $plan->status->value);
        $this->assertEquals(1750.00, $plan->realCost());
        $this->assertCount(2, $plan->sessions);
        $this->assertStringContainsString('Procedimientos realizados: Limpieza dental', $plan->sessions->pluck('objective')->join("\n"));

        // Concluido = solo lectura.
        $this->recordSession($quote, [])->assertSessionHas('toast.type', 'error');
        $this->get(route('records.treatments.edit', [$this->patient, $quote]))->assertSessionHas('toast.type', 'error');
    }

    public function test_sesion_valida_fecha_y_actividades(): void
    {
        $quote = $this->createPlan();
        $this->authorizePlan($quote);

        $this->recordSession($quote, [], ['performed_on' => now()->addDay()->toDateString(), 'activities' => ''])
            ->assertSessionHasErrorsIn('treatmentSession', ['performed_on', 'activities']);
    }

    public function test_modificar_plan_autorizado_lo_reemplaza_y_pide_nueva_autorizacion(): void
    {
        $quote = $this->createPlan();
        $this->authorizePlan($quote);

        $this->put(route('records.treatments.update', [$this->patient, $quote]), $this->payload([
            'procedures' => [['treatment_id' => $this->resin->id, 'tooth_fdi' => '36', 'unit_price' => '1100', 'notes' => 'oclusal']],
            'change_reason' => 'Cambio de costo',
        ]))->assertSessionHasNoErrors();

        $new = Quote::where('id', '!=', $quote->id)->firstOrFail();
        $old = $this->plan($quote);
        $current = $this->plan($new);

        $this->assertSame('cancelado', $old->status->value);
        $this->assertSame($new->folio, $old->replaced_by);
        $this->assertSame('propuesto', $current->status->value);
        $this->assertTrue($current->requires_reauthorization);
        $this->assertSame($quote->folio, $current->replaces);
        $this->assertEquals(1100.00, (float) $new->total);
        $this->assertDatabaseHas('notifications', ['entity_id' => $new->id, 'event_type' => 'treatment_plan.modified']);
        $this->assertSame(1, PatientTreatment::where('patient_id', $this->patient->id)->where('status', 'planeado')->count());
    }

    public function test_modificar_solo_el_diagnostico_no_crea_presupuesto(): void
    {
        $quote = $this->createPlan();

        $this->put(route('records.treatments.update', [$this->patient, $quote]), $this->payload(['diagnosis' => 'Caries profunda en 36.']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Quote::count());
        $this->assertSame('Caries profunda en 36.', $this->plan($quote)->diagnosis);
        $this->assertSame(1, ClinicalNote::count());
    }

    public function test_cancelar_exige_motivo_y_avisa_al_jefe(): void
    {
        $quote = $this->createPlan();

        $this->patch(route('records.treatments.cancel', [$this->patient, $quote]), ['reason' => ''])
            ->assertSessionHasErrorsIn('cancelPlan', ['reason']);

        $this->patch(route('records.treatments.cancel', [$this->patient, $quote]), ['reason' => 'El paciente no autorizó.'])
            ->assertSessionHas('toast.title', 'Tratamiento cancelado');

        $plan = $this->plan($quote);
        $this->assertSame('cancelado', $plan->status->value);
        $this->assertSame('El paciente no autorizó.', $plan->cancel_reason);
        $this->assertSame('rechazado', $quote->fresh()->status);
        $this->assertTrue($plan->procedures->every(fn ($pt) => $pt->status === 'cancelado'));
        $this->assertDatabaseHas('notifications', ['entity_id' => $quote->id, 'event_type' => 'treatment_plan.cancelled']);
    }

    public function test_plan_de_otro_paciente_da_404(): void
    {
        $quote = $this->createPlan();
        $other = $this->createPatient($this->doctor);

        $this->get(route('records.treatments.show', [$other, $quote]))->assertNotFound();
    }

    public function test_descarga_pdf(): void
    {
        $quote = $this->createPlan();

        $this->get(route('records.treatments.pdf', [$this->patient, $quote]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
