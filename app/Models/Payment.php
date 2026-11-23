<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pago de paciente. Versión mínima: lo implementa el módulo 9 (Pagos). Finanzas solo lo lee y
 * genera su movimiento con FinancialMovementService::recordFromPayment().
 */
class Payment extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'branch_id', 'patient_id', 'paid_at', 'amount', 'currency', 'method', 'reference',
        'status', 'idempotency_key', 'cash_session_id', 'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'method' => PaymentMethod::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function financialMovement(): HasOne
    {
        return $this->hasOne(FinancialMovement::class);
    }
}
