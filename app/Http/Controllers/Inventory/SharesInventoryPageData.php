<?php

namespace App\Http\Controllers\Inventory;

use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;

/** Datos que usan las dos pestañas de Inventario (Insumos y Movimientos): encabezado y modales. */
trait SharesInventoryPageData
{
    /** @return array<string, mixed> */
    protected function pageData(InventoryService $inventory): array
    {
        return [
            'summary' => $inventory->summary(),
            // RN-I10: solo los activos aparecen al registrar entradas y salidas.
            'activeItems' => InventoryItem::active()->with('stock')->orderBy('name')->get(),
            'supplierNames' => Supplier::active()->orderBy('name')->pluck('name'),
        ];
    }
}
