<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryMovementRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryService;
use Closure;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Módulo 10. Entradas y salidas de material (RF 10.2) e historial de movimientos. */
class InventoryMovementController extends Controller
{
    use SharesInventoryPageData;

    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'tipo' => InventoryMovementType::tryFrom((string) $request->query('tipo'))?->value ?? '',
        ];

        $movements = InventoryMovement::query()
            ->with(['item', 'actor', 'lot'])
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas('item', fn ($item) => $item->search($filters['q'])))
            ->when($filters['tipo'] !== '', fn ($q) => $q->where('movement_type', $filters['tipo']))
            ->orderByDesc('occurred_at')
            ->paginate(8)
            ->withQueryString();

        return view('inventory.movements', [
            'movements' => $movements,
            'filters' => $filters,
            ...$this->pageData($this->inventory),
        ]);
    }

    public function storeEntry(InventoryMovementRequest $request): RedirectResponse
    {
        $item = $request->item();

        return $this->attempt($item, function () use ($request, $item) {
            $movement = $this->inventory->recordEntry($item, $request->validated());

            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Entrada registrada',
                'message' => '+'.InventoryItem::formatQuantity($movement->quantity).' '.$item->unit_of_measure.' · '.$item->name,
            ]);
        });
    }

    public function storeExit(InventoryMovementRequest $request): RedirectResponse
    {
        $item = $request->item();

        return $this->attempt($item, function () use ($request, $item) {
            $this->inventory->recordExit($item, $request->validated());
            $item->load('stock');

            // RN-I8: si quedó en o por debajo del mínimo se avisa de inmediato (y la fila se pinta en el listado).
            return back()->with('toast', $item->isLowStock()
                ? ['type' => 'info', 'title' => 'Salida registrada · stock bajo', 'message' => $item->name.': quedan '.$item->quantityLabel().' (mínimo '.InventoryItem::formatQuantity($item->minQuantity()).')']
                : ['type' => 'success', 'title' => 'Salida registrada', 'message' => $item->name.': quedan '.$item->quantityLabel()]);
        });
    }

    /**
     * Convierte las reglas rotas en un toast de error: DomainException del Service
     * ("Stock insuficiente", material dado de baja) o SQLSTATE 45000 de los triggers.
     */
    private function attempt(InventoryItem $item, Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            $toast = $e->getMessage() === InventoryService::INSUFFICIENT_STOCK
                ? ['title' => InventoryService::INSUFFICIENT_STOCK, 'message' => 'Disponible: '.$item->load('stock')->quantityLabel().' de '.$item->name]
                : ['title' => 'No se pudo completar', 'message' => $e->getMessage()];
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== '45000') {
                throw $e;
            }
            $toast = ['title' => 'No se pudo completar', 'message' => $e->errorInfo[2] ?? 'La base de datos rechazó el movimiento.'];
        }

        return back()->with('toast', ['type' => 'error', ...$toast]);
    }
}
