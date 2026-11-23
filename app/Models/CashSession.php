<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apertura y corte de caja. Una vez cerrada es inmutable (trigger cash_sessions_closed_bu): el corte
 * se guarda con un solo UPDATE y las correcciones van en cash_session_adjustments.
 * opened_by / closed_by son users, no members.
 */
class CashSession extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'cash_register_id', 'opened_by', 'opened_at', 'opening_amount', 'status',
        'closed_by', 'closed_at', 'expected_cash', 'counted_cash', 'difference', 'notes',
    ];

    protected $attributes = ['status' => 'abierta'];

    protected $casts = [
        'status' => CashSessionStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialMovement::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CashSessionAdjustment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CashSessionStatus::Open->value);
    }
}
