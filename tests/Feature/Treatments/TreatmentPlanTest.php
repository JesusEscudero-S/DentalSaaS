<?php

namespace Tests\Feature\Treatments;

use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Módulo 7. Gestión de tratamientos. */
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

    private function createPlan(array $overrides = []): TreatmentPlan
    {
        $this->actingAsMember($this->doctor)
            ->post(route('records.treatments.store', $this->patient), $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return TreatmentPlan::latest('created_at')->firstOrFail();
    }

    private function authorizePlan(TreatmentPlan $plan): void
    {
        $this->patch(route('records.treatments.authorize', [$this->patient, $plan]), [
            'patient_agrees' => '1', 'accepted_by_name' => 'paciente prueba', 'method' => 'firma_autografa_escaneada',
        ])->assertSessionHas('toast.type', 'success');
    }

    private function recordSession(TreatmentPlan $plan, array $procedureIds, array $overrides = [])
    {
        return $this->post(route('records.treatments.sessions.store', [$this->patient, $plan]), [
            'performed_on' => now()->toDateString(),
            'activities' => 'Se realizó el procedimiento.',
            'doctor_member_id' => $this->doctor->id,
            'procedures' => $procedureIds,
            ...$overrides,
        ]);
    }

    public function test_crea_plan_propuesto_con_presupuesto_y_procedimientos(): void
    {
        $plan = $this->createPlan();

        $this->assertSame('propuesto', $plan->status->value);
        $this->assertSame('enviado', $plan->quote->status);
        $this->assertStringStartsWith('PRE-', $plan->quote->folio);
        $this->assertEquals(1750.00, (float) $plan->quote->total);

        $procedures = $plan->procedures();
        $this->assertCount(2, $procedures);
        $this->assertSame(36, $procedures[0]->tooth_fdi);
        $this->assertSame('Resina — oclusal', $procedures[0]->quoteItem->description_snapshot);
        $this->assertTrue($procedures->every(fn ($pt) => $pt->status === 'planeado'));
        $this->assertDatabaseHas('audit_log', ['entity_id' => $plan->id, 'action' => 'insert']);

        $this->get(route('records.treatments.index', $this->patient))->assertOk()->assertSee($plan->quote->folio);
        $this->get(route('records.treatments.show', [$this->patient, $plan]))->assertOk()->assertSee('Caries en 36');
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

        $this->assertSame(0, TreatmentPlan::count());
    }

    public function test_no_registra_sesiones_sin_autorizacion(): void
    {
        $plan = $this->createPlan();

        $this->recordSession($plan, [])->assertSessionHas('toast.type', 'error');
        $this->assertSame(0, DB::table('treatment_sessions')->count());
    }

    public function test_flujo_completo_autorizar_sesiones_y_concluir(): void
    {
        $plan = $this->createPlan();
        $this->authorizePlan($plan);

        $plan->refresh();
        $this->assertSame('autorizado', $plan->status->value);
        $this->assertSame('aprobado', $plan->quote->status);
        $this->assertNotNull($plan->consent_acceptance_id);

        [$resin, $cleaning] = $plan->procedures()->all();

        $this->recordSession($plan, [$resin->id])->assertSessionHas('toast.title', 'Sesión registrada');
        $this->assertSame('en_proceso', $plan->fresh()->status->value);
        $this->assertSame('realizado', $resin->fresh()->status);
        $this->assertDatabaseCount('clinical_encounters', 1);

        $this->recordSession($plan, [$cleaning->id])->assertSessionHas('toast.title', 'Tratamiento concluido');
        $plan->refresh();
        $this->assertSame('concluido', $plan->status->value);
        $this->assertEquals(1750.00, $plan->realCost());

        // Concluido = solo lectura.
        $this->recordSession($plan, [])->assertSessionHas('toast.type', 'error');
        $this->get(route('records.treatments.edit', [$this->patient, $plan]))->assertSessionHas('toast.type', 'error');
    }

    public function test_sesion_valida_fecha_y_actividades(): void
    {
        $plan = $this->createPlan();
        $this->authorizePlan($plan);

        $this->recordSession($plan, [], ['performed_on' => now()->addDay()->toDateString(), 'activities' => ''])
            ->assertSessionHasErrorsIn('treatmentSession', ['performed_on', 'activities']);
    }

    public function test_modificar_plan_autorizado_crea_version_y_requiere_nueva_autorizacion(): void
    {
        $plan = $this->createPlan();
        $this->authorizePlan($plan);
        $oldQuote = $plan->fresh()->quote;

        $this->put(route('records.treatments.update', [$this->patient, $plan]), $this->payload([
            'procedures' => [['treatment_id' => $this->resin->id, 'tooth_fdi' => '36', 'unit_price' => '1100', 'notes' => 'oclusal']],
            'change_reason' => 'Cambio de costo',
        ]))->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(2, $plan->version);
        $this->assertSame('propuesto', $plan->status->value);
        $this->assertTrue($plan->requires_reauthorization);
        $this->assertNotSame($oldQuote->id, $plan->quote_id);
        $this->assertSame('rechazado', $oldQuote->fresh()->status);
        $this->assertEquals(1100.00, (float) $plan->quote->total);
        $this->assertDatabaseCount('treatment_plan_versions', 2);
        $this->assertDatabaseHas('notifications', ['entity_id' => $plan->id, 'event_type' => 'treatment_plan.modified']);
        $this->assertSame(1, PatientTreatment::where('patient_id', $this->patient->id)->where('status', 'planeado')->count());
    }

    public function test_cancelar_exige_motivo_y_avisa_al_jefe(): void
    {
        $plan = $this->createPlan();

        $this->patch(route('records.treatments.cancel', [$this->patient, $plan]), ['reason' => ''])
            ->assertSessionHasErrorsIn('cancelPlan', ['reason']);

        $this->patch(route('records.treatments.cancel', [$this->patient, $plan]), ['reason' => 'El paciente no autorizó.'])
            ->assertSessionHas('toast.title', 'Tratamiento cancelado');

        $plan->refresh();
        $this->assertSame('cancelado', $plan->status->value);
        $this->assertSame('El paciente no autorizó.', $plan->cancel_reason);
        $this->assertSame('rechazado', $plan->quote->status);
        $this->assertTrue($plan->procedures()->every(fn ($pt) => $pt->status === 'cancelado'));
        $this->assertDatabaseHas('notifications', ['entity_id' => $plan->id, 'event_type' => 'treatment_plan.cancelled']);
    }

    public function test_correccion_de_sesion_con_nota(): void
    {
        $plan = $this->createPlan();
        $this->authorizePlan($plan);
        $this->recordSession($plan, []);
        $session = DB::table('treatment_sessions')->first();

        $this->post(route('records.treatments.sessions.notes.store', [$this->patient, $plan, $session->id]), ['note' => 'La pieza era 37.'])
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('treatment_session_notes', ['session_id' => $session->id, 'note' => 'La pieza era 37.']);
        $this->assertDatabaseCount('treatment_sessions', 1);
    }

    public function test_plan_de_otro_paciente_da_404(): void
    {
        $plan = $this->createPlan();
        $other = $this->createPatient($this->doctor);

        $this->get(route('records.treatments.show', [$other, $plan]))->assertNotFound();
    }

    public function test_descarga_pdf(): void
    {
        $plan = $this->createPlan();

        $this->get(route('records.treatments.pdf', [$this->patient, $plan]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
