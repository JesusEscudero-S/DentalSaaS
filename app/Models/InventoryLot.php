<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de un insumo con su fecha de caducidad (RF 10.1 / 10.2). Las entradas suman al lote y las
 * salidas descuentan primero de los lotes que caducan antes (InventoryService).
 */
class InventoryLot extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'branch_id', 'item_id', 'lot_code', 'expires_on', 'quantity_on_hand'];

    protected $casts = [
        'expires_on' => 'date',
        'quantity_on_hand' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
