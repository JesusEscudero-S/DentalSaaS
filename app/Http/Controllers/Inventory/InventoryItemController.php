<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryItemRequest;
use App\Models\InventoryItem;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Módulo 10. Catálogo de materiales: listado, alta (RF 10.1), edición y baja/reactivación (RF 10.3). */
class InventoryItemController extends Controller
{
    use SharesInventoryPageData;

    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $estado = (string) $request->query('estado', 'activos');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => InventoryCategory::tryFrom((string) $request->query('categoria'))?->value ?? '',
            'estado' => in_array($estado, ['activos', 'inactivos', 'todos'], true) ? $estado : 'activos',
            'stock' => $request->query('stock') === 'bajo' ? 'bajo' : '',
        ];

        $items = InventoryItem::query()
            ->with(['stock', 'supplier'])
            // Próxima caducidad de los lotes que aún tienen existencias.
            ->withMin(['lots as next_expiry' => fn ($q) => $q->where('quantity_on_hand', '>', 0)], 'expires_on')
            ->search($filters['q'])
            ->when($filters['categoria'] !== '', fn ($q) => $q->where('category', $filters['categoria']))
            ->when($filters['estado'] === 'activos', fn ($q) => $q->active())
            ->when($filters['estado'] === 'inactivos', fn ($q) => $q->where('status', 'inactive'))
            ->when($filters['stock'] === 'bajo', fn ($q) => $q->lowStock())
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            ...$this->pageData($this->inventory),
        ]);
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        try {
            $item = $this->inventory->create($request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.index')
            ->with('toast', ['type' => 'success', 'title' => 'Material registrado exitosamente', 'message' => $item->name]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        try {
            $this->inventory->update($item, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'title' => 'Material actualizado', 'message' => $item->name]);
    }

    public function deactivate(InventoryItem $item): RedirectResponse
    {
        $this->inventory->deactivate($item);

        return back()->with('toast', ['type' => 'success', 'title' => 'Material dado de baja', 'message' => $item->name]);
    }

    public function reactivate(InventoryItem $item): RedirectResponse
    {
        $this->inventory->reactivate($item);

        return back()->with('toast', ['type' => 'success', 'title' => 'Material reactivado', 'message' => $item->name]);
    }
}
