<?php

namespace App\Services\Treatments;

use App\Enums\ConsentMethod;
use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Enums\TreatmentPlanStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ConsentAcceptance;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Treatment;
use App\Services\AuditLogger;
use App\Services\FolioService;
use App\Services\Records\NoteService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo 7. Gestión de tratamientos, solo con tablas existentes:
 *  - Plan = presupuesto (quotes + quote_items) y un patient_treatments por procedimiento y pieza.
 *    Estatus del presupuesto: enviado (Propuesto) → aprobado (Autorizado / En proceso / Concluido) o rechazado (Cancelado).
 *  - Diagnóstico y sesiones = notas clínicas simples del Expediente (módulo 6), en borrador para que el
 *    doctor responsable las firme; su texto empieza con el folio del plan. Las correcciones se hacen
 *    editando el borrador o con adendas, como cualquier nota.
 *  - Autorización = consent_acceptances. Motivo de cancelación y reemplazos quedan en audit_log.
 */
class TreatmentPlanService
{
    public const DIAGNOSIS_PREFIX = 'Diagnóstico del plan de tratamiento ';

    public const SESSION_PREFIX = 'Sesión del plan de tratamiento ';

    public function __construct(
        private FolioService $folios,
        private AuditLogger $audit,
        private CurrentOrganization $current,
        private NoteService $notes,
    ) {}

    // ------------------------------------------------------------------ lectura

    /** @return Collection<int, TreatmentPlan> planes del paciente, del más reciente al más antiguo */
    public function plansFor(Patient $patient): Collection
    {
        return $patient->quotes()
            ->with(['doctor.user', 'items'])
            ->orderByDesc('quote_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Quote $quote) => $this->plan($quote, withSessions: false));
    }

    public function plan(Quote $quote, bool $withSessions = true): TreatmentPlan
    {
        $quote->loadMissing(['doctor.user', 'items', 'patient']);
        $procedures = $this->procedures($quote);
        $events = AuditLog::where('entity_type', $quote->getTable())->where('entity_id', $quote->id)->orderBy('id')->get();
        $field = fn (string $key) => $events->first(fn ($e) => isset(($e->changed_fields ?? [])[$key]));

        $authorized = $field('consent_acceptance_id');
        $cancelled = $events->last(fn ($e) => (($e->changed_fields ?? [])['status'][1] ?? null) === 'rechazado');
        $diagnosisNote = $this->notesWith(self::DIAGNOSIS_PREFIX.$quote->folio)->first();

        return new TreatmentPlan(
            quote: $quote,
            status: TreatmentPlanStatus::fromQuote($quote->status, $procedures),
            procedures: $procedures,
            diagnosisNote: $diagnosisNote,
            diagnosis: $diagnosisNote ? TreatmentPlan::body($diagnosisNote) : '',
            sessions: $withSessions ? $this->notesWith(self::SESSION_PREFIX.$quote->folio) : collect(),
            consent: $authorized ? ConsentAcceptance::find($authorized->changed_fields['consent_acceptance_id']) : null,
            authorized_at: $authorized?->created_at,
            cancelled_at: $cancelled?->created_at,
            cancel_reason: $cancelled?->changed_fields['reason'] ?? null,
            replaced_by: $field('replaced_by')?->changed_fields['replaced_by'],
            replaces: $field('replaces')?->changed_fields['replaces'],
            requires_reauthorization: (bool) $field('requires_reauthorization') && $quote->status === 'enviado',
        );
    }

    /** @return Collection<int, PatientTreatment> */
    public function procedures(Quote $quote): Collection
    {
        return PatientTreatment::query()
            ->with(['quoteItem', 'treatment'])
            ->whereIn('quote_item_id', QuoteItem::where('quote_id', $quote->id)->select('id'))
            ->get()
            ->sortBy(fn ($pt) => $pt->quoteItem->line_number)
            ->values();
    }

    // ------------------------------------------------------------------ acciones

    /**
     * Diagrama 1, pasos 4-9: presupuesto "enviado" (plan Propuesto) + nota con el diagnóstico.
     *
     * @param  array{doctor_member_id: string, diagnosis: string, diagnosed_on: string, procedures: list<array<string, mixed>>}  $data
     */
    public function create(Patient $patient, array $data): Quote
    {
        if (! $patient->isActive()) {
            throw new DomainException('El paciente está inactivo: reactívalo para crear un tratamiento.');
        }

        return DB::transaction(function () use ($patient, $data) {
            $quote = $this->createQuote($patient, $data);
            $this->writeDiagnosis($patient, $quote, $data);
            $this->audit->log($quote, AuditLogger::INSERT, ['module' => 'treatments', 'folio' => $quote->folio]);

            return $quote;
        });
    }

    /**
     * Diagrama 3, rama "Editar" (solo antes de la primera sesión). Si cambian procedimientos o costos se crea
     * un presupuesto nuevo y el anterior queda rechazado ("reemplazado por"). Si el plan estaba Autorizado,
     * el nuevo requiere autorización otra vez (RN-T5) y se avisa al jefe (RN-T6).
     *
     * @param  array<string, mixed>  $data
     * @return Quote el presupuesto vigente (el mismo o el nuevo)
     */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->with(['items', 'patient'])->firstOrFail();
            $status = TreatmentPlanStatus::fromQuote($quote->status, $this->procedures($quote));

            if (! $status->isEditable()) {
                throw new DomainException('Este tratamiento ya tiene sesiones o está cerrado: no se puede modificar.');
            }

            if (! $this->proceduresChanged($quote, $data['procedures'])) {
                $quote->update(['doctor_member_id' => $data['doctor_member_id'], 'quote_date' => $data['diagnosed_on']]);
                $this->writeDiagnosis($quote->patient, $quote, $data);
                $this->audit->log($quote, AuditLogger::UPDATE, ['fields' => ['doctor', 'diagnosis']]);

                return $quote;
            }

            $from = $quote->status;
            $new = $this->createQuote($quote->patient, $data);
            PatientTreatment::whereIn('quote_item_id', $quote->items()->select('id'))->update(['status' => 'cancelado']);
            $this->setQuoteStatus($quote, 'rechazado');

            $wasAuthorized = $status === TreatmentPlanStatus::Authorized;
            $this->audit->log($quote, AuditLogger::UPDATE, ['status' => [$from, 'rechazado'], 'replaced_by' => $new->folio]);
            $this->audit->log($new, AuditLogger::INSERT, array_filter([
                'module' => 'treatments', 'folio' => $new->folio, 'replaces' => $quote->folio,
                'requires_reauthorization' => $wasAuthorized ?: null,
                'reason' => $data['change_reason'] ?? null,
            ]));
            $this->writeDiagnosis($quote->patient, $new, $data, $quote);

            if ($wasAuthorized) {
                $this->alertChief($new, 'modified', 'Se modificó un tratamiento autorizado',
                    "{$quote->patient->full_name}: {$quote->folio} se reemplazó por {$new->folio}. Requiere nueva autorización.");
            }

            return $new;
        });
    }

    /** Diagrama 1, pasos 10-11: el paciente autoriza (consentimiento) y el presupuesto queda aprobado. */
    public function authorize(Quote $quote, string $acceptedBy, ConsentMethod $method): void
    {
        DB::transaction(function () use ($quote, $acceptedBy, $method) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->ensureTransition($quote, TreatmentPlanStatus::Authorized);

            $consent = ConsentAcceptance::create([
                'patient_id' => $quote->patient_id,
                'accepted_at' => now(),
                'accepted_by_name' => $acceptedBy,
                'method' => $method,
                'witness_member_id' => $this->current->member()?->id,
            ]);

            $this->setQuoteStatus($quote, 'aprobado');
            $this->audit->log($quote, AuditLogger::UPDATE, ['status' => ['enviado', 'aprobado'], 'consent_acceptance_id' => $consent->id]);
        });
    }

    /**
     * Diagrama 2: la sesión es una nota clínica (borrador para que la firme el doctor) y los procedimientos
     * marcados pasan a realizados. Cuando no queda ninguno pendiente, el plan se concluye (lo calcula el estatus).
     *
     * @param  array{performed_on: string, activities: string, doctor_member_id: string, procedures?: list<string>}  $data
     */
    public function recordSession(Quote $quote, array $data): ClinicalNote
    {
        return DB::transaction(function () use ($quote, $data) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->with('patient')->firstOrFail();
            $procedures = $this->procedures($quote);
            $status = TreatmentPlanStatus::fromQuote($quote->status, $procedures);

            if (! $status->acceptsSessions()) {
                throw new DomainException($status->isClosed()
                    ? 'El tratamiento está cerrado: no se pueden registrar sesiones.'
                    : 'El tratamiento todavía no está autorizado por el paciente.');
            }

            $selected = $procedures->whereIn('id', $data['procedures'] ?? []);
            if ($selected->contains(fn ($pt) => $pt->status !== 'planeado')) {
                throw new DomainException('Uno de los procedimientos ya estaba realizado.');
            }

            $performedOn = CarbonImmutable::parse($data['performed_on']);
            if ($performedOn->lt($quote->quote_date)) {
                throw new DomainException('La sesión no puede ser anterior al diagnóstico.');
            }

            $done = $selected->map(fn ($pt) => $pt->quoteItem->description_snapshot.($pt->tooth_fdi ? " (pieza {$pt->tooth_fdi})" : ''));
            $text = self::SESSION_PREFIX.$quote->folio."\n".trim($data['activities'])
                .($done->isNotEmpty() ? "\nProcedimientos realizados: ".$done->join(', ').'.' : '');

            $note = $this->notes->create($quote->patient, [
                'note_type' => NoteType::Simple->value,
                'doctor_member_id' => $data['doctor_member_id'],
                'occurred_on' => $performedOn->toDateString(),
                'objective' => $text,
            ], $this->current->member(), false, 'treatmentSession');

            if ($selected->isNotEmpty()) {
                PatientTreatment::whereKey($selected->pluck('id'))->update([
                    'status' => 'realizado',
                    'started_at' => $performedOn->toDateTimeString(),
                    'completed_at' => $performedOn->toDateTimeString(),
                ]);
            }

            return $note;
        });
    }

    /**
     * Diagrama 3, rama "Cancelar": motivo obligatorio (queda en la bitácora), procedimientos pendientes
     * cancelados, presupuesto rechazado y aviso al jefe.
     *
     * @return int citas futuras del paciente con servicios del plan (para avisar en Agenda)
     */
    public function cancel(Quote $quote, string $reason): int
    {
        return DB::transaction(function () use ($quote, $reason) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->with('patient')->firstOrFail();
            $this->ensureTransition($quote, TreatmentPlanStatus::Cancelled);

            $procedures = $this->procedures($quote);
            PatientTreatment::whereKey($procedures->where('status', '!=', 'realizado')->pluck('id'))->update(['status' => 'cancelado']);
            $from = $quote->status;
            $this->setQuoteStatus($quote, 'rechazado');

            $this->audit->log($quote, AuditLogger::UPDATE, ['status' => [$from, 'rechazado'], 'reason' => Str::limit($reason, 500)]);
            $this->alertChief($quote, 'cancelled', 'Se canceló un tratamiento',
                "{$quote->patient->full_name} ({$quote->folio}). Motivo: ".Str::limit($reason, 140));

            return DB::table('appointments')
                ->where('organization_id', $quote->organization_id)
                ->where('patient_id', $quote->patient_id)
                ->whereIn('treatment_id', $procedures->pluck('treatment_id')->unique())
                ->where('starts_at', '>', now())
                ->whereIn('status', ['pendiente', 'confirmada'])
                ->count();
        });
    }

    /** Odontólogos activos que pueden ser responsables. */
    public static function doctors()
    {
        return OrganizationMember::doctors()->with('user')->get()->sortBy(fn ($m) => $m->displayName())->values();
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

            $item = QuoteItem::create([
                'quote_id' => $quote->id,
                'line_number' => $i + 1,
                'treatment_id' => $treatment->id,
                'description_snapshot' => $this->description($treatment->name, $row['notes'] ?? null),
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

    /**
     * Guarda el diagnóstico como nota clínica del Expediente. Si ya hay una en borrador (del mismo plan o del
     * que reemplaza) se actualiza; si está firmada no se toca y se crea otra.
     *
     * @param  array<string, mixed>  $data
     */
    private function writeDiagnosis(Patient $patient, Quote $quote, array $data, ?Quote $previous = null): void
    {
        $text = self::DIAGNOSIS_PREFIX.$quote->folio."\n".trim($data['diagnosis']);
        $existing = $this->notesWith(self::DIAGNOSIS_PREFIX.($previous ?? $quote)->folio)->first();

        if ($existing && $existing->status === NoteStatus::Borrador) {
            $existing->update(['objective' => $text, 'responsible_doctor_member_id' => $data['doctor_member_id']]);

            return;
        }

        $this->notes->create($patient, [
            'note_type' => NoteType::Simple->value,
            'doctor_member_id' => $data['doctor_member_id'],
            'occurred_on' => $data['diagnosed_on'],
            'objective' => $text,
        ], $this->current->member(), false, 'treatmentPlan');
    }

    /** @return Collection<int, ClinicalNote> notas cuyo texto empieza con $prefix (el folio es único por clínica) */
    private function notesWith(string $prefix): Collection
    {
        return ClinicalNote::query()
            ->with(['encounter', 'responsibleDoctor.user', 'addenda'])
            ->where('objective', 'like', addcslashes($prefix, '%_\\')."\n%")
            ->orderByDesc('created_at')
            ->get();
    }

    private function description(string $name, ?string $notes): string
    {
        return Str::limit($name.(filled($notes) ? ' — '.$notes : ''), 297);
    }

    /** ¿Cambió algún servicio, pieza, costo u observación respecto al presupuesto? */
    private function proceduresChanged(Quote $quote, array $procedures): bool
    {
        $key = fn ($treatmentId, $tooth, $price, $description) => implode('|', [$treatmentId, $tooth ?: '', number_format((float) $price, 2, '.', ''), $description]);
        $current = $quote->items->map(fn (QuoteItem $i) => $key($i->treatment_id, $i->tooth_fdi, $i->unit_price, $i->description_snapshot))->all();
        $names = Treatment::whereKey(collect($procedures)->pluck('treatment_id'))->pluck('name', 'id');

        $new = collect($procedures)->values()->map(fn ($r) => $key(
            $r['treatment_id'], $r['tooth_fdi'] ?? null, $r['unit_price'], $this->description($names[$r['treatment_id']] ?? '', $r['notes'] ?? null),
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
            'created_at' => Carbon::now(),
        ];
    }

    /** RN-T6: alerta para el jefe de odontología (tabla notifications, visible en su panel). */
    private function alertChief(Quote $quote, string $event, string $title, string $body): void
    {
        DB::table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'organization_id' => $quote->organization_id,
            'event_type' => "treatment_plan.{$event}",
            'title' => $title,
            'body' => $body,
            'entity_type' => $quote->getTable(),
            'entity_id' => $quote->id,
            'dedup_key' => "treatment_plan.{$event}:{$quote->id}",
            'created_at' => now(),
        ]);
    }

    private function ensureTransition(Quote $quote, TreatmentPlanStatus $to): void
    {
        $from = TreatmentPlanStatus::fromQuote($quote->status, $this->procedures($quote));

        if (! $from->canTransitionTo($to)) {
            throw new DomainException("Un tratamiento {$from->label()} no puede pasar a {$to->label()}.");
        }
    }
}
