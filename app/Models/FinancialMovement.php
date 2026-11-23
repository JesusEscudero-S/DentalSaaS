<?php

namespace App\Models;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Libro de ingresos y egresos. Inmutable (D4): una corrección es una reversión, otro movimiento con
 * reversed_of_id apuntando al original.
 */
class FinancialMovement extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'branch_id', 'direction', 'category_id', 'concept', 'occurred_on', 'amount',
        'currency', 'method', 'payment_id', 'cash_session_id', 'receipt_storage_key', 'created_by',
        'reversed_of_id',
    ];

    protected $casts = [
        'direction' => MovementDirection::class,
        'method' => PaymentMethod::class,
        'occurred_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Movimiento original que esta reversión anula. */
    public function reversedOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_of_id');
    }

    /** Reversión que anula este movimiento. */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversed_of_id');
    }

    public function isReversal(): bool
    {
        return $this->reversed_of_id !== null;
    }

    public function isReversed(): bool
    {
        return $this->reversal !== null;
    }

    /** RF-03: motivo por el que no se puede revertir, o null si sí se puede. */
    public function reversalBlocker(): ?string
    {
        return match (true) {
            $this->isReversal() => 'Una reversión no se puede revertir.',
            $this->isReversed() => 'Este movimiento ya fue revertido.',
            $this->payment_id !== null => 'Este ingreso proviene de un pago; reviértelo desde Pagos',
            $this->method === PaymentMethod::Cash && $this->cashSession && ! $this->cashSession->isOpen() => 'La caja ya fue cortada; registra un ajuste de caja',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isReversal() => 'Reversión',
            $this->isReversed() => 'Revertido',
            default => 'Vigente',
        };
    }

    /**
     * Datos para el modal "Detalle del movimiento".
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        return [
            'date' => Ui::date($this->occurred_on),
            'direction' => $this->direction->label(),
            'concept' => $this->concept,
            'category' => $this->category?->name,
            'branch' => $this->branch?->name ?? '—',
            'method' => $this->method->label(),
            'cash_register' => $this->cashSession?->register?->name,
            'amount' => ($this->direction === MovementDirection::Income ? '+' : '−').Ui::money($this->amount),
            'created_by' => $this->creator?->full_name ?? '—',
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'status' => $this->statusLabel(),
            'from_payment' => $this->payment_id !== null,
            'receipt_url' => $this->receipt_storage_key ? route('finances.movements.receipt', $this) : null,
        ];
    }

    /**
     * Totales vigentes (RF-04): excluye las reversiones y los movimientos que tienen una reversión.
     */
    public function scopeEffective(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query
            ->whereNull($this->qualifyColumn('reversed_of_id'))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from("{$table} as reversals")
                ->whereColumn('reversals.organization_id', "{$table}.organization_id")
                ->whereColumn('reversals.reversed_of_id', "{$table}.id"));
    }
}
