<?php

namespace App\Services\Treatments;

use App\Enums\TreatmentPlanStatus;
use App\Models\ClinicalNote;
use App\Models\ConsentAcceptance;
use App\Models\OrganizationMember;
use App\Models\PatientTreatment;
use App\Models\Quote;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Módulo 7. Vista de un plan de tratamiento armada solo con tablas existentes (sin tablas propias):
 *  - el plan es el presupuesto (quotes) con sus partidas (quote_items) y procedimientos (patient_treatments);
 *  - el diagnóstico y cada sesión son notas clínicas del Expediente (clinical_notes) cuyo texto empieza
 *    con el folio del plan (ver TreatmentPlanService::DIAGNOSIS_PREFIX / SESSION_PREFIX);
 *  - la autorización es un consent_acceptances, y el motivo de cancelación queda en audit_log.
 */
class TreatmentPlan
{
    /**
     * @param  Collection<int, PatientTreatment>  $procedures
     * @param  Collection<int, ClinicalNote>  $sessions
     */
    public function __construct(
        public readonly Quote $quote,
        public readonly TreatmentPlanStatus $status,
        public readonly Collection $procedures,
        public readonly ?ClinicalNote $diagnosisNote,
        public readonly string $diagnosis,
        public readonly Collection $sessions,
        public readonly ?ConsentAcceptance $consent,
        public readonly ?CarbonInterface $authorized_at,
        public readonly ?CarbonInterface $cancelled_at,
        public readonly ?string $cancel_reason,
        public readonly ?string $replaced_by,
        public readonly ?string $replaces,
        public readonly bool $requires_reauthorization,
    ) {}

    public function doctor(): ?OrganizationMember
    {
        return $this->quote->doctor;
    }

    public function diagnosedOn(): CarbonInterface
    {
        return $this->quote->quote_date;
    }

    /** Fecha en que se realizó el último procedimiento (cuando está concluido). */
    public function completedAt(): ?CarbonInterface
    {
        return $this->status === TreatmentPlanStatus::Completed
            ? $this->procedures->max('completed_at')
            : null;
    }

    public function realCost(): float
    {
        return (float) $this->procedures
            ->where('status', 'realizado')
            ->sum(fn (PatientTreatment $pt) => (float) $pt->quoteItem->line_total);
    }

    /** Texto de una nota de sesión sin el encabezado con el folio. */
    public static function body(ClinicalNote $note): string
    {
        return trim(preg_replace('/^[^\n]*\n/', '', (string) $note->objective, 1));
    }
}
