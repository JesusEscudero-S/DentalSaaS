<?php

namespace App\Services\Treatments;

use App\Enums\ConsentMethod;
use App\Enums\TreatmentPlanStatus;
use App\Models\Branch;
use App\Models\ClinicalEncounter;
use App\Models\ConsentAcceptance;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanVersion;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionNote;
use App\Services\AuditLogger;
use App\Services\FolioService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo 7. Gestión de tratamientos.
 *
 * Plan = presupuesto (quotes) con sus procedimientos (quote_items + patient_treatments), igual que lo
 * usa el módulo Documentos para la "Autorización de tratamiento". Estatus del presupuesto según el plan:
 * Propuesto → enviado · Autorizado/En proceso/Concluido → aprobado · Cancelado o versión anterior → rechazado.
 */
class TreatmentPlanService
{
    public function __construct(
        private FolioService $folios,
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * Diagrama 1, pasos 4-9: guarda el plan como Propuesto con su presupuesto.
     *
     * @param  array{doctor_member_id: string, diagnosis: string, diagnosed_on: string, procedures: list<array<string, mixed>>}  $data
     */
    public function create(Patient $patient, array $data): TreatmentPlan
    {
        if (! $patient->isActive()) {
            throw new DomainException('El paciente está inactivo: reactívalo para crear un tratamiento.');
        }

        return DB::transaction(function () use ($patient, $data) {
            MedicalRecord::firstOrCreate(['patient_id' => $patient->id]); // precondición: expediente

            $quote = $this->createQuote($patient, $data);

            $plan = TreatmentPlan::create([
                'patient_id' => $patient->id,
                'doctor_member_id' => $data['doctor_member_id'],
                'quote_id' => $quote->id,
                'diagnosis' => $data['diagnosis'],
                'diagnosed_on' => $data['diagnosed_on'],
                'status' => TreatmentPlanStatus::Proposed,
                'version' => 1,
                'created_by_user_id' => Auth::id(),
            ]);

            TreatmentPlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'quote_id' => $quote->id, 'created_by_user_id' => Auth::id()]);

            $this->audit->log($plan, AuditLogger::INSERT, ['folio' => $quote->folio, 'total' => $quote->total]);

            return $plan;
        });
    }

    /**
     * Diagrama 3, rama "Editar". Solo antes de la primera sesión. Si cambian procedimientos o costos se crea
     * una versión nueva con otro presupuesto (P8); si el plan estaba Autorizado vuelve a Propuesto y requiere
     * nueva autorización (RN-T5), y se avisa al jefe (RN-T6).
     *
     * @param  array<string, mixed>  $data  + change_reason
     */
    public function update(TreatmentPlan $plan, array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan = $this->lock($plan);

            if (! $plan->status->isEditable()) {
                throw new DomainException('Este tratamiento ya tiene sesiones o está cerrado: no se puede modificar.');
            }

            $changed = [];
            foreach (['doctor_member_id', 'diagnosis', 'diagnosed_on'] as $field) {
                $plan->{$field} = $data[$field];
            }
            $changed = array_keys($plan->getDirty());

            if ($this->proceduresChanged($plan, $data['procedures'])) {
                $wasAuthorized = $plan->status === TreatmentPlanStatus::Authorized;
                $oldQuote = $plan->quote;

                $quote = $this->createQuote($plan->patient, $data);
                $this->setQuoteStatus($oldQuote, 'rechazado');
                PatientTreatment::whereIn('quote_item_id', $oldQuote->items()->select('id'))->update(['status' => 'cancelado']);

                $plan->quote_id = $quote->id;
                $plan->version++;
                if ($wasAuthorized) {
                    $plan->status = TreatmentPlanStatus::Proposed;
                    $plan->requires_reauthorization = true;
                }

                TreatmentPlanVersion::create([
                    'plan_id' => $plan->id, 'version' => $plan->version, 'quote_id' => $quote->id,
                    'change_reason' => $data['change_reason'] ?? null, 'created_by_user_id' => Auth::id(),
                ]);
                $changed[] = 'procedures';

                if ($wasAuthorized) {
                    $this->alertChief($plan, 'modified', 'Se modificó un tratamiento autorizado',
                        "{$plan->patient->full_name}: nueva versión {$plan->version} ({$quote->folio}). Requiere nueva autorización.");
                }
            }

            if ($changed) {
                $plan->save();
                $this->audit->log($plan, AuditLogger::UPDATE, ['fields' => $changed, 'version' => $plan->version]);
            }

            return $plan;
        });
    }

    /** Diagrama 1, pasos 10-11: el paciente autoriza el plan (consentimiento registrado). */
    public function authorize(TreatmentPlan $plan, string $acceptedBy, ConsentMethod $method): void
    {
        DB::transaction(function () use ($plan, $acceptedBy, $method) {
            $plan = $this->lock($plan);
            $this->ensureTransition($plan, TreatmentPlanStatus::Authorized);

            $consent = ConsentAcceptance::create([
                'patient_id' => $plan->patient_id,
                'accepted_at' => now(),
                'accepted_by_name' => $acceptedBy,
                'method' => $method,
                'witness_member_id' => $this->current->member()?->id,
            ]);

            $this->setQuoteStatus($plan->quote, 'aprobado');
            $plan->update([
                'status' => TreatmentPlanStatus::Authorized,
                'requires_reauthorization' => false,
                'consent_acceptance_id' => $consent->id,
                'authorized_at' => now(),
            ]);

            $this->audit->log($plan, AuditLogger::UPDATE, ['status' => [TreatmentPlanStatus::Proposed->value, 'autorizado']]);
        });
    }

    /**
     * Diagrama 2: registra una sesión, marca los procedimientos realizados y, si ya no queda ninguno
     * pendiente, concluye el plan (su costo real queda disponible para Facturación y pagos).
     *
     * @param  array{performed_on: string, activities: string, doctor_member_id: string, procedures?: list<string>}  $data
     */
    public function recordSession(TreatmentPlan $plan, array $data): TreatmentSession
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan = $this->lock($plan);

            if (! $plan->status->acceptsSessions()) {
                throw new DomainException($plan->status->isClosed()
                    ? 'El tratamiento está cerrado: no se pueden registrar sesiones.'
                    : 'El tratamiento todavía no está autorizado por el paciente.');
            }

            $procedures = $plan->procedures();
            $selected = $procedures->whereIn('id', $data['procedures'] ?? []);

            if ($selected->contains(fn ($pt) => $pt->status !== 'planeado')) {
                throw new DomainException('Uno de los procedimientos ya estaba realizado.');
            }

            $performedOn = CarbonImmutable::parse($data['performed_on']);
            if ($performedOn->lt($plan->diagnosed_on)) {
                throw new DomainException('La sesión no puede ser anterior al diagnóstico.');
            }

            $encounter = ClinicalEncounter::create([
                'medical_record_id' => MedicalRecord::firstOrCreate(['patient_id' => $plan->patient_id])->id,
                'branch_id' => $plan->quote->branch_id,
                'doctor_member_id' => $data['doctor_member_id'],
                'occurred_at' => $performedOn->setTimeFrom(now()),
            ]);

            $session = TreatmentSession::create([
                'plan_id' => $plan->id,
                'encounter_id' => $encounter->id,
                'doctor_member_id' => $data['doctor_member_id'],
                'performed_on' => $performedOn->toDateString(),
                'activities' => $data['activities'],
                'created_by_user_id' => Auth::id(),
            ]);

            if ($selected->isNotEmpty()) {
                $session->procedures()->attach($selected->pluck('id'));
                PatientTreatment::whereKey($selected->pluck('id'))->update([
                    'status' => 'realizado',
                    'started_at' => DB::raw('COALESCE(started_at, '.DB::getPdo()->quote($performedOn->toDateTimeString()).')'),
                    'completed_at' => $performedOn->toDateTimeString(),
                ]);
            }

            $pending = $procedures->where('status', 'planeado')->count() - $selected->count();
            $from = $plan->status;
            $plan->status = $pending === 0 ? TreatmentPlanStatus::Completed : TreatmentPlanStatus::InProgress;
            if ($plan->status === TreatmentPlanStatus::Completed) {
                $plan->completed_at = now();
            }
            $plan->save();

            $this->audit->log($session, AuditLogger::INSERT, ['procedures' => $selected->count()]);
            if ($from !== $plan->status) {
                $this->audit->log($plan, AuditLogger::UPDATE, ['status' => [$from->value, $plan->status->value]]);
            }

            return $session;
        });
    }

    /** RN-T4: las sesiones no se editan; se corrigen con una nota. */
    public function addSessionNote(TreatmentSession $session, string $note): TreatmentSessionNote
    {
        return DB::transaction(function () use ($session, $note) {
            $correction = TreatmentSessionNote::create(['session_id' => $session->id, 'note' => $note, 'author_user_id' => Auth::id()]);
            $this->audit->log($session, AuditLogger::UPDATE, ['correction_note' => $correction->id]);

            return $correction;
        });
    }

    /**
     * Diagrama 3, rama "Cancelar": motivo obligatorio, los procedimientos pendientes se cancelan y se avisa al jefe.
     *
     * @return int citas futuras del paciente con servicios del plan (para avisar en Agenda)
     */
    public function cancel(TreatmentPlan $plan, string $reason): int
    {
        return DB::transaction(function () use ($plan, $reason) {
            $plan = $this->lock($plan);
            $this->ensureTransition($plan, TreatmentPlanStatus::Cancelled);

            $from = $plan->status;
            $procedures = $plan->procedures();
            PatientTreatment::whereKey($procedures->where('status', '!=', 'realizado')->pluck('id'))->update(['status' => 'cancelado']);
            $this->setQuoteStatus($plan->quote, 'rechazado');

            $plan->update(['status' => TreatmentPlanStatus::Cancelled, 'cancel_reason' => $reason, 'cancelled_at' => now()]);

            $this->audit->log($plan, AuditLogger::UPDATE, ['status' => [$from->value, 'cancelado']]);
            $this->alertChief($plan, 'cancelled', 'Se canceló un tratamiento',
                "{$plan->patient->full_name} ({$plan->quote->folio}). Motivo: ".Str::limit($reason, 140));

            return DB::table('appointments')
                ->where('organization_id', $plan->organization_id)
                ->where('patient_id', $plan->patient_id)
                ->whereIn('treatment_id', $procedures->pluck('treatment_id')->unique())
                ->where('starts_at', '>', now())
                ->whereIn('status', ['pendiente', 'confirmada'])
                ->count();
        });
    }

    // ------------------------------------------------------------------ internos

    /** @param  array<string, mixed>  $data */
    private function createQuote(Patient $patient, array $data): Quote
    {
        $treatments = Treatment::active()->whereKey(collect($data['procedures'])->pluck('treatment_id'))->get()->keyBy('id');

        $quote = Quote::create([
            'folio' => $this->folios->next('quote', 'PRE-'),
            'patient_id' => $patient->id,
            'doctor_member_id' => $data['doctor_member_id'],
            'branch_id' => Branch::orderByDesc('is_main')->orderBy('name')->firstOrFail()->id,
            'quote_date' => $data['diagnosed_on'],
            'status' => 'enviado',
            'sent_at' => now(),
        ]);
        DB::table('quote_status_history')->insert($this->history($quote, null, 'enviado'));

        $total = 0.0;
        foreach (array_values($data['procedures']) as $i => $row) {
            $treatment = $treatments[$row['treatment_id']] ?? throw new DomainException('Uno de los servicios ya no existe en el catálogo.');
            $price = round((float) $row['unit_price'], 2);
            $description = $treatment->name.(filled($row['notes'] ?? null) ? ' — '.$row['notes'] : '');

            $item = QuoteItem::create([
                'quote_id' => $quote->id,
                'line_number' => $i + 1,
                'treatment_id' => $treatment->id,
                'description_snapshot' => Str::limit($description, 297),
                'sat_product_key_snapshot' => $treatment->sat_product_key,
                'tooth_fdi' => $row['tooth_fdi'] ?? null,
                'quantity' => 1,
                'unit_price' => $price,
                'line_subtotal' => $price,
                'line_discount' => 0,
                'line_tax' => 0,
                'line_total' => $price,
            ]);

            PatientTreatment::create([
                'patient_id' => $patient->id,
                'treatment_id' => $treatment->id,
                'quote_item_id' => $item->id,
                'tooth_fdi' => $item->tooth_fdi,
                'status' => 'planeado',
            ]);

            $total += $price;
        }

        $quote->update(['subtotal' => $total, 'total' => $total]);

        return $quote;
    }

    /** ¿Cambió algún servicio, pieza, costo u observación respecto al presupuesto vigente? */
    private function proceduresChanged(TreatmentPlan $plan, array $procedures): bool
    {
        $key = fn ($treatmentId, $tooth, $price, $description) => implode('|', [$treatmentId, $tooth ?: '', number_format((float) $price, 2, '.', ''), $description]);

        $current = $plan->quote->items->map(fn (QuoteItem $i) => $key($i->treatment_id, $i->tooth_fdi, $i->unit_price, $i->description_snapshot))->all();

        $names = Treatment::whereKey(collect($procedures)->pluck('treatment_id'))->pluck('name', 'id');
        $new = collect($procedures)->values()->map(fn ($r) => $key(
            $r['treatment_id'], $r['tooth_fdi'] ?? null, $r['unit_price'],
            Str::limit(($names[$r['treatment_id']] ?? '').(filled($r['notes'] ?? null) ? ' — '.$r['notes'] : ''), 297),
        ))->all();

        return $current !== $new;
    }

    private function setQuoteStatus(Quote $quote, string $status): void
    {
        if ($quote->status === $status) {
            return;
        }

        DB::table('quote_status_history')->insert($this->history($quote, $quote->status, $status));
        $quote->update(['status' => $status]);
    }

    /** @return array<string, mixed> */
    private function history(Quote $quote, ?string $from, string $to): array
    {
        return [
            'id' => (string) Str::uuid(),
            'organization_id' => $quote->organization_id,
            'quote_id' => $quote->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => Auth::id(),
            'created_at' => now(),
        ];
    }

    /** RN-T6 / P8: alerta para el jefe de odontología (tabla notifications, visible en su panel). */
    private function alertChief(TreatmentPlan $plan, string $event, string $title, string $body): void
    {
        DB::table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'organization_id' => $plan->organization_id,
            'event_type' => "treatment_plan.{$event}",
            'title' => $title,
            'body' => $body,
            'entity_type' => $plan->getTable(),
            'entity_id' => $plan->id,
            'dedup_key' => "treatment_plan.{$event}:{$plan->id}:v{$plan->version}",
            'created_at' => now(),
        ]);
    }

    private function ensureTransition(TreatmentPlan $plan, TreatmentPlanStatus $to): void
    {
        if (! $plan->status->canTransitionTo($to)) {
            throw new DomainException("Un tratamiento {$plan->status->label()} no puede pasar a {$to->label()}.");
        }
    }

    private function lock(TreatmentPlan $plan): TreatmentPlan
    {
        return TreatmentPlan::whereKey($plan->id)->lockForUpdate()->with(['quote.items', 'patient'])->firstOrFail();
    }

    /** Odontólogos activos que pueden ser responsables. */
    public static function doctors()
    {
        return OrganizationMember::doctors()->with('user')->get()->sortBy(fn ($m) => $m->displayName())->values();
    }
}
