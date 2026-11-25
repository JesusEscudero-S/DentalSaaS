<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de existencias. Inmutable (triggers inventory_movements_bu/bd): un error se corrige con
 * otro movimiento. Al insertarse, el trigger inventory_movements_ai actualiza inventory_stock.
 */
class InventoryMovement extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false; // la tabla solo tiene occurred_at

    protected $fillable = [
        'organization_id', 'branch_id', 'item_id', 'movement_type', 'quantity', 'unit_cost', 'reference',
        'reason', 'occurred_at', 'actor_user_id', 'encounter_id', 'lot_id', 'reverses_movement_id',
    ];

    protected $casts = [
        'movement_type' => InventoryMovementType::class,
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }

    public function isIncoming(): bool
    {
        return bccomp((string) $this->quantity, '0', 4) > 0;
    }
}
