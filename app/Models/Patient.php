<?php

namespace App\Models;

use App\Enums\PatientSex;
use App\Enums\PatientStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Paciente (módulo 3).
 *
 * - Desactivar = status inactive (RN-P3). `deleted_at` y `anonymized_at` quedan reservados para la
 *   política legal de anonimización; los registros con deleted_at se ocultan siempre (SoftDeletes).
 * - La edad no se guarda (RN-P2): se calcula desde birth_date.
 */
class Patient extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id', 'folio', 'full_name', 'birth_date', 'sex', 'phone', 'email',
        'assigned_doctor_member_id', 'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'sex' => PatientSex::class,
        'status' => PatientStatus::class,
        'anonymized_at' => 'datetime',
    ];

    // Relaciones

    public function assignedDoctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'assigned_doctor_member_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(PatientGuardian::class)->orderByDesc('is_primary')->orderBy('full_name');
    }

    public function fiscalProfile(): HasOne
    {
        return $this->hasOne(PatientFiscalProfile::class)->where('is_default', true);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function consentAcceptances(): HasMany
    {
        return $this->hasMany(ConsentAcceptance::class);
    }

    // Atributos calculados

    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->birth_date?->age);
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::get(fn () => Ui::phone($this->phone));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => Ui::initials($this->full_name));
    }

    protected function avatarColor(): Attribute
    {
        return Attribute::get(fn () => Ui::colorFor($this->id ?? $this->full_name));
    }

    /**
     * Datos para precargar el modal "Editar paciente".
     *
     * @return array<string, mixed>
     */
    public function toFormArray(): array
    {
        $fiscal = $this->relationLoaded('fiscalProfile') ? $this->fiscalProfile : $this->fiscalProfile()->first();

        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date?->toDateString(),
            'sex' => $this->sex?->value,
            'phone' => Ui::phone($this->phone),
            'email' => $this->email,
            'assigned_doctor_member_id' => $this->assigned_doctor_member_id ?? '',
            'rfc' => $fiscal?->rfc,
            'legal_name' => $fiscal?->legal_name,
            'tax_regime_code' => $fiscal?->tax_regime_code ?? '',
            'fiscal_postal_code' => $fiscal?->fiscal_postal_code,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === PatientStatus::Active;
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PatientStatus::Active->value);
    }

    /** Búsqueda por nombre, folio, teléfono o email (RF 3.2 + buscador de Figma). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $q->where('full_name', 'like', $like)
                ->orWhere('folio', 'like', $like)
                ->orWhere('email', 'like', $like);

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /** Agrega `last_visit_at`: última cita completada. */
    public function scopeWithLastVisit(Builder $query): Builder
    {
        return $query->addSelect([
            'last_visit_at' => DB::table('appointments')
                ->selectRaw('MAX(starts_at)')
                ->whereColumn('appointments.organization_id', 'patients.organization_id')
                ->whereColumn('appointments.patient_id', 'patients.id')
                ->where('appointments.status', 'completada'),
        ]);
    }

    /**
     * Agrega `balance`: total facturado vigente menos pagos aplicados no revertidos.
     * Se ajustará cuando se implemente el módulo 9 (Pagos).
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        $validInvoices = fn ($q) => $q->whereColumn('invoices.organization_id', 'patients.organization_id')
            ->whereColumn('invoices.patient_id', 'patients.id')
            ->whereNotIn('invoices.fiscal_status', ['borrador', 'cancelada', 'error']);

        return $query->addSelect([
            'invoiced_total' => DB::table('invoices')->selectRaw('COALESCE(SUM(total), 0)')->where($validInvoices),
            'paid_total' => DB::table('payment_applications')
                ->join('invoices', 'invoices.id', '=', 'payment_applications.invoice_id')
                ->selectRaw('COALESCE(SUM(payment_applications.applied_amount), 0)')
                ->whereNull('payment_applications.reversed_at')
                ->where($validInvoices),
        ]);
    }

    protected function balance(): Attribute
    {
        return Attribute::get(fn () => round((float) ($this->invoiced_total ?? 0) - (float) ($this->paid_total ?? 0), 2));
    }
}
