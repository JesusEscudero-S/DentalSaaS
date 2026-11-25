<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de la clínica demo: los 8 insumos de Figma con su historial de entradas y salidas.
 * Al final quedan las mismas existencias que el diseño (5 en o por debajo del mínimo y un valor
 * de $32,323.00). Usa InventoryService, así que el stock lo calcula el trigger como en la app.
 * Es idempotente: si la clínica ya tiene insumos no hace nada.
 */
class InventorySeeder extends Seeder
{
    /**
     * [nombre, SKU, categoría, unidad, proveedor, stock inicial, mínimo, costo, ubicación, lote, caduca en (días),
     *  movimientos: [tipo, cantidad, hace (días), referencia]]
     */
    private const ITEMS = [
        ['Anestesia Lidocaína 2%', 'ANS-LID-002', 'Anestésicos', 'cartucho', 'Dental Depot', 20, 20, '18.50', 'Estante A-2', 'LID-2611', 240,
            [['salida', 5, 20, 'Consultas de la semana'], ['salida', 7, 6, 'Consultas de la semana']]],
        ['Guantes nitrilo (M)', 'GNT-NIT-M', 'Protección', 'caja', 'MediSupply', 10, 10, '165.00', 'Gaveta B-1', null, null,
            [['salida', 8, 3, 'Consumo semanal']]],
        ['Resina compuesta A2', 'RES-A2-3M', 'Restaurativos', 'jeringa', '3M ESPE', 3, 5, '420.00', 'Refrigerador', 'A2-0925', 25,
            [['salida', 2, 10, 'Restauraciones clase II']]],
        ['Fresas diamante surtidas', 'FRS-DIA-12', 'Instrumental', 'paquete', 'Dental Depot', 6, 6, '240.00', 'Gaveta C-3', null, null,
            [['salida', 6, 2, 'Reposición de fresas en gabinetes']]],
        ['Algodón rollos #2', 'ALG-R2-500', 'Consumibles', 'paquete', 'MediSupply', 25, 20, '85.00', 'Estante A-1', null, null,
            [['entrada', 20, 12, 'Factura MS-4471']]],
        ['Cemento ionómero', 'CMT-ION-15', 'Restaurativos', 'frasco', 'GC América', 12, 8, '310.00', 'Estante A-3', null, null, []],
        ['Eyectores de saliva', 'EYC-SAL-100', 'Consumibles', 'bolsa', 'MediSupply', 200, 50, '120.00', 'Gaveta B-2', null, null,
            [['salida', 20, 8, 'Consumo semanal']]],
        ['Hilo de sutura 4-0', 'SUT-40-12', 'Cirugía', 'caja', 'Ethicon', 6, 10, '380.00', 'Gaveta C-1', 'SUT-2603', 300, []],
    ];

    public function run(InventoryService $inventory, CurrentOrganization $current): void
    {
        $organization = Organization::where('commercial_name', DemoOrganizationSeeder::ORGANIZATION)->firstOrFail();

        $admin = OrganizationMember::where('organization_id', $organization->id)
            ->whereHas('role', fn ($q) => $q->where('code', 'admin'))
            ->with('user')
            ->firstOrFail();
        $current->set($admin);

        if (InventoryItem::exists()) {
            $current->clear();

            return;
        }

        DB::transaction(function () use ($inventory) {
            foreach (self::ITEMS as [$name, $sku, $category, $unit, $supplier, $initial, $min, $cost, $location, $lot, $expiresIn, $movements]) {
                $item = $inventory->create([
                    'name' => $name,
                    'sku' => $sku,
                    'category' => $category,
                    'unit_of_measure' => $unit,
                    'supplier_name' => $supplier,
                    'initial_quantity' => $initial,
                    'min_quantity' => $min,
                    'unit_cost' => $cost,
                    'location' => $location,
                    'lot_code' => $lot,
                    'expires_on' => $expiresIn ? today()->addDays($expiresIn)->toDateString() : null,
                    'occurred_at' => now()->subDays(45)->setTime(9, 0),
                ]);

                foreach ($movements as [$type, $quantity, $daysAgo, $reference]) {
                    $data = [
                        'quantity' => $quantity,
                        'reference' => $reference,
                        'unit_cost' => $type === 'entrada' ? $cost : null,
                        'occurred_at' => now()->subDays($daysAgo)->setTime(13, 30),
                    ];

                    $type === 'entrada' ? $inventory->recordEntry($item, $data) : $inventory->recordExit($item, $data);
                }
            }
        });

        $current->clear();
    }
}
