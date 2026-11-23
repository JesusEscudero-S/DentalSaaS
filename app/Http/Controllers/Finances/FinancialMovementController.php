<?php

namespace App\Http\Controllers\Finances;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\ReverseFinancialMovementRequest;
use App\Http\Requests\Finances\StoreFinancialMovementRequest;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Services\Finances\FinanceReportService;
use App\Services\Finances\FinancialMovementService;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialMovementController extends Controller
{
    private const LIST_RELATIONS = ['category', 'branch', 'creator', 'reversal', 'cashSession.register'];

    public function __construct(
        private FinancialMovementService $movements,
        private FinanceReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $movements = $this->listQuery($query, $filters)
            ->paginate(8)
            ->withQueryString();

        return view('finances.movements.index', [
            'movements' => $movements,
            'filters' => $filters,
            'totals' => $this->reports->totals($query),
            'categories' => FinancialCategory::orderBy('name')->get(),
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(),
            'openSessions' => CashSession::open()->with(['register', 'openedBy'])->get(),
        ]);
    }

    /** RF-01 / RF-02 */
    public function store(StoreFinancialMovementRequest $request): RedirectResponse
    {
        $movement = $this->movements->record($request->safe()->except('receipt'), $request->file('receipt'));

        return redirect()
            ->route('finances.movements.index')
            ->with('toast', ['type' => 'success', 'title' => 'Movimiento registrado correctamente', 'message' => $movement->concept.' · '.Ui::money($movement->amount)]);
    }

    /** RF-03 */
    public function reverse(ReverseFinancialMovementRequest $request, FinancialMovement $movement): RedirectResponse
    {
        $this->movements->reverse($movement, $request->validated('reason'));

        return back()->with('toast', ['type' => 'success', 'title' => 'Movimiento revertido', 'message' => $movement->concept]);
    }

    public function receipt(FinancialMovement $movement): StreamedResponse
    {
        $key = $movement->receipt_storage_key;
        abort_unless($key && Storage::disk('local')->exists($key), 404);

        return Storage::disk('local')->download($key, 'comprobante-'.$movement->occurred_on->format('Y-m-d').'.'.pathinfo($key, PATHINFO_EXTENSION));
    }

    /** CSV del listado con los filtros aplicados. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->listQuery($this->filteredQuery($filters), $filters)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
            fputcsv($out, ['Fecha', 'Tipo', 'Concepto', 'Categoría', 'Sucursal', 'Método', 'Caja', 'Monto', 'Moneda', 'Registrado por', 'Estado', 'Origen']);

            $query->chunk(500, function ($movements) use ($out) {
                foreach ($movements as $m) {
                    fputcsv($out, [
                        $m->occurred_on->format('d/m/Y'), $m->direction->label(), $this->csvText($m->concept),
                        $this->csvText($m->category?->name), $this->csvText($m->branch?->name), $m->method->label(),
                        $this->csvText($m->cashSession?->register?->name), $m->amount, $m->currency,
                        $m->creator?->full_name, $m->statusLabel(), $m->payment_id ? 'Pago de paciente' : 'Manual',
                    ]);
                }
            });

            fclose($out);
        }, 'movimientos-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{q: string, tipo: string, categoria: string, metodo: string, sucursal: string, desde: string, hasta: string, revertidos: bool} */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'tipo' => MovementDirection::tryFrom((string) $request->query('tipo'))?->value ?? '',
            'categoria' => (string) $request->query('categoria', ''),
            'metodo' => PaymentMethod::tryFrom((string) $request->query('metodo'))?->value ?? '',
            'sucursal' => (string) $request->query('sucursal', ''),
            'desde' => $this->dateFilter($request->query('desde')),
            'hasta' => $this->dateFilter($request->query('hasta')),
            'revertidos' => $request->boolean('revertidos'),
        ];
    }

    /** Filtros comunes al listado, al CSV y a los totales (sin el de revertidos). */
    private function filteredQuery(array $filters): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';

        return FinancialMovement::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where('concept', 'like', $like))
            ->when($filters['tipo'] !== '', fn ($q) => $q->where('direction', $filters['tipo']))
            ->when($filters['categoria'] !== '', fn ($q) => $q->where('category_id', $filters['categoria']))
            ->when($filters['metodo'] !== '', fn ($q) => $q->where('method', $filters['metodo']))
            ->when($filters['sucursal'] !== '', fn ($q) => $q->where('branch_id', $filters['sucursal']))
            ->when($filters['desde'] !== '', fn ($q) => $q->where('occurred_on', '>=', $filters['desde']))
            ->when($filters['hasta'] !== '', fn ($q) => $q->where('occurred_on', '<=', $filters['hasta']));
    }

    /** RF-04: sin "Incluir revertidos" solo se listan los movimientos vigentes. */
    private function listQuery(Builder $query, array $filters): Builder
    {
        return (clone $query)
            ->when(! $filters['revertidos'], fn ($q) => $q->effective())
            ->with(self::LIST_RELATIONS)
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at');
    }

    private function dateFilter(mixed $value): string
    {
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    /** Evita que Excel interprete un texto capturado por el usuario como fórmula. */
    private function csvText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
