<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Existencias de un insumo en una sucursal (inventory_stock, llave (branch_id, item_id)).
 *
 * quantity_on_hand y avg_unit_cost los actualiza SOLO el trigger inventory_movements_ai al
 * insertar un movimiento; el código nunca los modifica directamente. Aquí solo se escriben
 * min_quantity y location. Como Eloquent no maneja llaves compuestas, las actualizaciones se
 * hacen con where(branch_id, item_id) en InventoryService.
 */
class InventoryStock extends Model
{
    use BelongsToOrganization;

    protected $table = 'inventory_stock';

    protected $primaryKey = 'item_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false; // updated_at lo pone MySQL (ON UPDATE CURRENT_TIMESTAMP)

    protected $fillable = ['organization_id', 'branch_id', 'item_id', 'min_quantity', 'location', 'avg_unit_cost'];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'min_quantity' => 'decimal:4',
        'avg_unit_cost' => 'decimal:4',
        'updated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
