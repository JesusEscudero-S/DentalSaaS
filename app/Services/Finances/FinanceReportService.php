<?php

namespace App\Services\Finances;

use App\Enums\MovementDirection;
use App\Models\CashSession;
use App\Models\FinancialMovement;
use App\Support\Ui;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Agregados financieros. Siempre sobre totales vigentes (RF-04) y en SQL. */
class FinanceReportService
{
    private const INCOME_SUM = "COALESCE(SUM(CASE WHEN financial_movements.direction = 'ingreso' THEN financial_movements.amount END), 0)";

    private const EXPENSE_SUM = "COALESCE(SUM(CASE WHEN financial_movements.direction = 'egreso' THEN financial_movements.amount END), 0)";

    /**
     * @param  Builder<FinancialMovement>  $query  movimientos ya filtrados
     * @return array{income: string, expense: string, net: string}
     */
    public function totals(Builder $query): array
    {
        $row = (clone $query)->effective()->toBase()
            ->selectRaw(self::INCOME_SUM.' AS income')
            ->selectRaw(self::EXPENSE_SUM.' AS expense')
            ->first();

        $income = bcadd((string) $row->income, '0', 2);
        $expense = bcadd((string) $row->expense, '0', 2);

        return ['income' => $income, 'expense' => $expense, 'net' => bcsub($income, $expense, 2)];
    }

    /**
     * Ingresos y egresos de los últimos $months meses, terminando en el mes de $lastMonth.
     *
     * @param  Builder<FinancialMovement>  $query  movimientos filtrados (sin filtro de fechas)
     * @return list<array{month: string, label: string, income: string, expense: string}>
     */
    public function monthlySeries(Builder $query, CarbonInterface $lastMonth, int $months = 6): array
    {
        $first = $lastMonth->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);

        $rows = (clone $query)->effective()
            ->whereBetween('financial_movements.occurred_on', [$first->toDateString(), $lastMonth->copy()->endOfMonth()->toDateString()])
            ->toBase()
            ->selectRaw("DATE_FORMAT(financial_movements.occurred_on, '%Y-%m') AS ym")
            ->selectRaw(self::INCOME_SUM.' AS income')
            ->selectRaw(self::EXPENSE_SUM.' AS expense')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $first->copy()->addMonthsNoOverflow($i);
            $row = $rows->get($month->format('Y-m'));
            $series[] = [
                'month' => $month->format('Y-m'),
                'label' => mb_substr(Ui::date($month), mb_strpos(Ui::date($month), ' ') + 1),
                'income' => bcadd((string) ($row->income ?? '0'), '0', 2),
                'expense' => bcadd((string) ($row->expense ?? '0'), '0', 2),
            ];
        }

        return $series;
    }

    /**
     * Desglose por categoría de un tipo de movimiento. Incluye categorías inactivas (RF-05).
     *
     * @param  Builder<FinancialMovement>  $query  movimientos ya filtrados
     * @return array{total: string, rows: list<array{name: string, total: string, share: string}>}
     */
    public function byCategory(Builder $query, MovementDirection $direction): array
    {
        $rows = (clone $query)->effective()
            ->where('financial_movements.direction', $direction->value)
            ->join('financial_categories', 'financial_categories.id', '=', 'financial_movements.category_id')
            ->toBase()
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->orderByDesc('total')
            ->get(['financial_categories.name', DB::raw('SUM(financial_movements.amount) AS total')]);

        $total = $rows->reduce(fn (string $carry, $row) => bcadd($carry, (string) $row->total, 2), '0.00');

        return [
            'total' => $total,
            'rows' => $rows->map(fn ($row) => [
                'name' => $row->name,
                'total' => bcadd((string) $row->total, '0', 2),
                'share' => bccomp($total, '0', 2) > 0 ? bcdiv(bcmul((string) $row->total, '100', 4), $total, 1) : '0.0',
            ])->all(),
        ];
    }

    /** @return array{count: int, opening: string} sesiones de caja abiertas y su monto de apertura */
    public function openCash(?string $branchId = null): array
    {
        $row = CashSession::open()
            ->when($branchId, fn ($q) => $q->whereHas('register', fn ($r) => $r->where('branch_id', $branchId)))
            ->toBase()
            ->selectRaw('COUNT(*) AS sessions, COALESCE(SUM(opening_amount), 0) AS opening')
            ->first();

        return ['count' => (int) $row->sessions, 'opening' => bcadd((string) $row->opening, '0', 2)];
    }

    /** Variación porcentual con un decimal (null si el periodo anterior es 0). */
    public function change(string $current, string $previous): ?string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        return bcdiv(bcmul(bcsub($current, $previous, 2), '100', 4), ltrim($previous, '-'), 1);
    }
}
