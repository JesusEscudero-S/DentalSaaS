<?php

namespace App\Services\Inventory;

use App\Enums\ActiveStatus;
use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\Ui;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Módulo 10. Gestión de inventario.
 *
 * - Las existencias (inventory_stock.quantity_on_hand) y el costo promedio las calcula el trigger
 *   inventory_movements_ai al insertar cada movimiento: aquí nunca se suman ni restan a mano.
 * - Los movimientos son inmutables (triggers): un error se corrige con otro movimiento.
 * - La clínica trabaja con una sola sucursal (la principal).
 */
class InventoryService
{
    /** Mensaje textual del PDF (RN-I6). */
    public const INSUFFICIENT_STOCK = 'Stock insuficiente';

    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * RF 10.1: alta de material con stock inicial, mínimo, costo, ubicación y lote opcional.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InventoryItem
    {
        return DB::transaction(function () use ($data) {
            $branchId = $this->branchId();
            $unitCost = $this->money($data['unit_cost'] ?? null) ?? '0';
            $initial = (int) ($data['initial_quantity'] ?? 0);

            $item = InventoryItem::create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'category' => $data['category'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'],
                'supplier_id' => $this->supplierId($data['supplier_name'] ?? null),
            ]);

            // La fila de existencias nace en 0 con el costo capturado; el movimiento de
            // inventario inicial (si hay cantidad) la actualiza mediante el trigger.
            InventoryStock::create([
                'branch_id' => $branchId,
                'item_id' => $item->id,
                'min_quantity' => (int) $data['min_quantity'],
                'location' => $data['location'] ?? null,
                'avg_unit_cost' => $unitCost,
            ]);

            if ($initial > 0) {
                $lot = $this->addToLot($item, $branchId, $data['lot_code'] ?? null, $data['expires_on'] ?? null, $initial);

                $this->insertMovement($item, $branchId, InventoryMovementType::InitialStock, (string) $initial, [
                    'unit_cost' => $unitCost,
                    'reference' => 'Alta de material',
                    'lot_id' => $lot?->id,
                    'occurred_at' => $data['occurred_at'] ?? null,
                ]);
            }

            $this->audit->log($item, AuditLogger::INSERT, [
                'name' => $item->name,
                'sku' => $item->sku,
                'initial_quantity' => $initial,
                'min_quantity' => (int) $data['min_quantity'],
            ]);

            return $item->load(['stock', 'supplier']);
        });
    }

    /**
     * Edición de los datos del catálogo, el mínimo y la ubicación. El stock no se edita aquí:
     * solo cambia con entradas y salidas (RN-I7).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item->fill([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'category' => $data['category'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'],
                'supplier_id' => $this->supplierId($data['supplier_name'] ?? null),
            ]);
            $changed = array_keys($item->getDirty());
            $item->save();

            $branchId = $this->branchId();
            $stock = $this->stockQuery($item, $branchId)->first();
            $newStock = ['min_quantity' => (int) $data['min_quantity'], 'location' => $data['location'] ?? null];

            if (! $stock) {
                InventoryStock::create(['branch_id' => $branchId, 'item_id' => $item->id, ...$newStock]);
                $changed = [...$changed, 'min_quantity', 'location'];
            } else {
                if (bccomp((string) $stock->min_quantity, (string) $newStock['min_quantity'], 4) !== 0) {
                    $changed[] = 'min_quantity';
                }
                if ((string) $stock->location !== (string) $newStock['location']) {
                    $changed[] = 'location';
                }
                $this->stockQuery($item, $branchId)->update($newStock);
            }

            if ($changed) {
                $this->audit->log($item, AuditLogger::UPDATE, ['fields' => array_values(array_unique($changed))]);
            }

            return $item->load(['stock', 'supplier']);
        });
    }

    /** RF 10.3 / RN-I9: baja lógica. */
    public function deactivate(InventoryItem $item): void
    {
        $this->changeStatus($item, ActiveStatus::Inactive);
    }

    public function reactivate(InventoryItem $item): void
    {
        $this->changeStatus($item, ActiveStatus::Active);
    }

    /**
     * RF 10.2: entrada de material (compra, donación…). Si trae lote, suma a ese lote.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordEntry(InventoryItem $item, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($item, $data) {
            $this->ensureActive($item);
            $branchId = $this->branchId();
            $quantity = (int) $data['quantity'];

            // Bloquea la fila de existencias para serializar movimientos simultáneos del mismo insumo.
            if (! $this->stockQuery($item, $branchId)->lockForUpdate()->first()) {
                InventoryStock::create(['branch_id' => $branchId, 'item_id' => $item->id]);
            }

            $lot = $this->addToLot($item, $branchId, $data['lot_code'] ?? null, $data['expires_on'] ?? null, $quantity);

            return $this->insertMovement($item, $branchId, InventoryMovementType::Entry, (string) $quantity, [
                'unit_cost' => $this->money($data['unit_cost'] ?? null),
                'reference' => $data['reference'] ?? null,
                'lot_id' => $lot?->id,
                'occurred_at' => $data['occurred_at'] ?? null,
            ]);
        });
    }

    /**
     * RF 10.2: salida (consumo) de material.
     * RN-I6: nunca se descuenta más de lo que hay ("Stock insuficiente").
     * Descuenta primero de los lotes que caducan antes.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordExit(InventoryItem $item, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($item, $data) {
            $this->ensureActive($item);
            $branchId = $this->branchId();
            $quantity = (int) $data['quantity'];

            $stock = $this->stockQuery($item, $branchId)->lockForUpdate()->first();

            if (! $stock || bccomp((string) $stock->quantity_on_hand, (string) $quantity, 4) < 0) {
                throw new DomainException(self::INSUFFICIENT_STOCK);
            }

            $this->consumeLots($item, $branchId, $quantity);

            return $this->insertMovement($item, $branchId, InventoryMovementType::Exit, (string) -$quantity, [
                'reference' => $data['reference'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? null,
            ]);
        });
    }

    /**
     * Totales del encabezado: insumos activos, valor del inventario (existencias × costo promedio)
     * y cuántos están en o por debajo del mínimo (RN-I8).
     *
     * @return array{count: int, value: string, low: int}
     */
    public function summary(): array
    {
        $value = InventoryStock::query()
            ->whereHas('item', fn ($q) => $q->active())
            ->sum(DB::raw('quantity_on_hand * avg_unit_cost'));

        return [
            'count' => InventoryItem::active()->count(),
            'value' => number_format((float) $value, 2, '.', ''),
            'low' => InventoryItem::lowStock()->count(),
        ];
    }

    private function changeStatus(InventoryItem $item, ActiveStatus $status): void
    {
        if ($item->status === $status) {
            return;
        }

        DB::transaction(function () use ($item, $status) {
            $from = $item->status->value;
            $item->update(['status' => $status]);
            $this->audit->log($item, AuditLogger::UPDATE, ['status' => [$from, $status->value]]);
        });
    }

    /**
     * RN-I10: un material dado de baja no admite movimientos. Se relee con bloqueo para que una
     * baja simultánea no se cruce con el movimiento.
     */
    private function ensureActive(InventoryItem $item): void
    {
        $current = InventoryItem::whereKey($item->getKey())->lockForUpdate()->first();

        if (! $current?->isActive()) {
            throw new DomainException('El material está dado de baja; reactívalo para registrar movimientos.');
        }
    }

    /**
     * @param  array{unit_cost?: ?string, reference?: ?string, lot_id?: ?string, occurred_at?: mixed}  $extra
     */
    private function insertMovement(InventoryItem $item, string $branchId, InventoryMovementType $type, string $quantity, array $extra): InventoryMovement
    {
        return InventoryMovement::create([
            'branch_id' => $branchId,
            'item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $extra['unit_cost'] ?? null,
            'reference' => $extra['reference'] ?? null,
            'lot_id' => $extra['lot_id'] ?? null,
            'occurred_at' => $extra['occurred_at'] ?? now(),
            'actor_user_id' => $this->current->member()->user_id,
        ]);
    }

    /** Suma la cantidad al lote indicado (lo crea si no existe). Sin código de lote no hace nada. */
    private function addToLot(InventoryItem $item, string $branchId, ?string $code, ?string $expiresOn, int $quantity): ?InventoryLot
    {
        if ($code === null || $code === '') {
            return null;
        }

        $lot = InventoryLot::where('branch_id', $branchId)
            ->where('item_id', $item->id)
            ->where('lot_code', $code)
            ->lockForUpdate()
            ->first();

        if (! $lot) {
            return InventoryLot::create([
                'branch_id' => $branchId,
                'item_id' => $item->id,
                'lot_code' => $code,
                'expires_on' => $expiresOn,
                'quantity_on_hand' => $quantity,
            ]);
        }

        if ($expiresOn && $lot->expires_on && $lot->expires_on->toDateString() !== $expiresOn) {
            throw new DomainException("El lote {$code} ya está registrado con caducidad ".Ui::date($lot->expires_on).'.');
        }

        $lot->expires_on ??= $expiresOn;
        $lot->quantity_on_hand = bcadd((string) $lot->quantity_on_hand, (string) $quantity, 4);
        $lot->save();

        return $lot;
    }

    /** Descuenta la salida de los lotes con existencias, primero los que caducan antes. */
    private function consumeLots(InventoryItem $item, string $branchId, int $quantity): void
    {
        $remaining = (string) $quantity;

        $lots = InventoryLot::where('branch_id', $branchId)
            ->where('item_id', $item->id)
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('lot_code')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = (string) $lot->quantity_on_hand;
            $taken = bccomp($available, $remaining, 4) < 0 ? $available : $remaining;

            $lot->quantity_on_hand = bcsub($available, $taken, 4);
            $lot->save();
            $remaining = bcsub($remaining, $taken, 4);
        }
    }

    /** Busca el proveedor por nombre (sin distinguir mayúsculas) o lo crea. */
    private function supplierId(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Supplier::where('name', $name)->value('id') ?? Supplier::create(['name' => $name])->id;
    }

    /** Sucursal donde vive el inventario: la principal (la clínica usa una sola). */
    private function branchId(): string
    {
        $id = Branch::where('status', ActiveStatus::Active->value)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->value('id');

        if (! $id) {
            throw new DomainException('La clínica no tiene una sucursal activa para el inventario.');
        }

        return $id;
    }

    private function stockQuery(InventoryItem $item, string $branchId): Builder
    {
        return InventoryStock::where('branch_id', $branchId)->where('item_id', $item->id);
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
