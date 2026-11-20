<?php

namespace App\Http\Controllers\Finances;

use App\Enums\ActiveStatus;
use App\Enums\MovementDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\FinancialCategoryRequest;
use App\Models\FinancialCategory;
use App\Services\Finances\FinancialCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** RF-05 */
class FinancialCategoryController extends Controller
{
    public function __construct(private FinancialCategoryService $categories) {}

    public function index(Request $request): View
    {
        $estado = $request->query('estado', 'todas');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'tipo' => MovementDirection::tryFrom((string) $request->query('tipo'))?->value ?? '',
            'estado' => in_array($estado, ['activas', 'inactivas', 'todas'], true) ? $estado : 'todas',
        ];
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';

        $categories = FinancialCategory::query()
            ->withCount('movements')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', $like))
            ->when($filters['tipo'] !== '', fn ($q) => $q->where('direction', $filters['tipo']))
            ->when($filters['estado'] === 'activas', fn ($q) => $q->where('status', ActiveStatus::Active->value))
            ->when($filters['estado'] === 'inactivas', fn ($q) => $q->where('status', ActiveStatus::Inactive->value))
            ->orderBy('direction')
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        return view('finances.categories.index', ['categories' => $categories, 'filters' => $filters]);
    }

    public function store(FinancialCategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create($request->validated());

        return redirect()
            ->route('finances.categories.index')
            ->with('toast', ['type' => 'success', 'title' => 'Categoría creada', 'message' => $category->name]);
    }

    public function update(FinancialCategoryRequest $request, FinancialCategory $category): RedirectResponse
    {
        $this->categories->update($category, $request->validated());

        return back()->with('toast', ['type' => 'success', 'title' => 'Categoría actualizada', 'message' => $category->name]);
    }

    public function deactivate(FinancialCategory $category): RedirectResponse
    {
        $this->categories->deactivate($category);

        return back()->with('toast', ['type' => 'success', 'title' => 'Categoría desactivada', 'message' => $category->name]);
    }

    public function reactivate(FinancialCategory $category): RedirectResponse
    {
        $this->categories->reactivate($category);

        return back()->with('toast', ['type' => 'success', 'title' => 'Categoría reactivada', 'message' => $category->name]);
    }
}
