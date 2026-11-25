<?php

namespace App\Models;

use App\Enums\TreatmentPlanStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Módulo 7. Plan de tratamiento de un paciente. Sus procedimientos son las partidas del
 * presupuesto vigente (quote_id) y sus patient_treatments.
 */
class TreatmentPlan extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id', 'patient_id', 'doctor_member_id', 'quote_id', 'diagnosis', 'diagnosed_on', 'status',
        'version', 'requires_reauthorization', 'consent_acceptance_id', 'authorized_at', 'cancel_reason',
        'cancelled_at', 'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'diagnosed_on' => 'date',
        'status' => TreatmentPlanStatus::class,
        'version' => 'integer',
        'requires_reauthorization' => 'boolean',
        'authorized_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'doctor_member_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(ConsentAcceptance::class, 'consent_acceptance_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TreatmentPlanVersion::class, 'plan_id')->orderByDesc('version');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class, 'plan_id')->orderByDesc('performed_on')->orderByDesc('created_at');
    }

    /**
     * Procedimientos del presupuesto vigente, cada uno con su patient_treatment (estatus por pieza).
     *
     * @return Collection<int, PatientTreatment>
     */
    public function procedures(): Collection
    {
        return PatientTreatment::query()
            ->with(['quoteItem', 'treatment'])
            ->whereIn('quote_item_id', QuoteItem::where('quote_id', $this->quote_id)->select('id'))
            ->get()
            ->sortBy(fn ($pt) => $pt->quoteItem->line_number)
            ->values();
    }

    /** Costo estimado: total del presupuesto vigente. */
    public function estimatedCost(): float
    {
        return (float) $this->quote->total;
    }

    /** Costo real: lo ya realizado (lo que pasa a Facturación y pagos al concluir). */
    public function realCost(?Collection $procedures = null): float
    {
        return (float) ($procedures ?? $this->procedures())
            ->where('status', 'realizado')
            ->sum(fn ($pt) => (float) $pt->quoteItem->line_total);
    }
}
