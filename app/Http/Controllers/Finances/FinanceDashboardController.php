<?php

namespace App\Http\Controllers\Finances;

use App\Enums\MovementDirection;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FinancialMovement;
use App\Services\Finances\FinanceReportService;
use App\Support\Ui;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Finanzas › Resumen: KPIs, ingresos vs. egresos de 6 meses y desglose por categoría. */
class FinanceDashboardController extends Controller
{
    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function __construct(private FinanceReportService $reports) {}

    public function index(Request $request): View
    {
        $period = $this->period($request);
        $branch = (string) $request->query('sucursal', '');

        $scope = fn (): Builder => FinancialMovement::query()->when($branch !== '', fn ($q) => $q->where('financial_movements.branch_id', $branch));
        $between = fn (CarbonImmutable $from, CarbonImmutable $to): Builder => $scope()
            ->whereBetween('financial_movements.occurred_on', [$from->toDateString(), $to->toDateString()]);

        $inPeriod = $between($period['from'], $period['to']);
        $current = $this->reports->totals($inPeriod);
        $previous = $this->reports->totals($between($period['previous_from'], $period['previous_to']));

        return view('finances.index', [
            'period' => $period,
            'branch' => $branch,
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(),
            'monthOptions' => $this->monthOptions(),
            'current' => $current,
            'changes' => [
                'income' => $this->reports->change($current['income'], $previous['income']),
                'expense' => $this->reports->change($current['expense'], $previous['expense']),
                'net' => $this->reports->change($current['net'], $previous['net']),
            ],
            'openCash' => $this->reports->openCash($branch !== '' ? $branch : null),
            'series' => $this->reports->monthlySeries($scope(), $period['to']),
            'incomeByCategory' => $this->reports->byCategory($inPeriod, MovementDirection::Income),
            'expenseByCategory' => $this->reports->byCategory($inPeriod, MovementDirection::Expense),
            'latest' => (clone $inPeriod)->effective()->with('category')
                ->orderByDesc('occurred_on')->orderByDesc('created_at')->limit(5)->get(),
        ]);
    }

    /**
     * Mes (`mes=2026-11`, por defecto el actual) o rango (`desde`/`hasta`), y el periodo anterior
     * con el que se compara. El mes en curso se compara contra los mismos días del mes anterior.
     *
     * @return array{custom: bool, mes: string, desde: string, hasta: string, from: CarbonImmutable, to: CarbonImmutable,
     *     previous_from: CarbonImmutable, previous_to: CarbonImmutable, label: string, compare_label: string}
     */
    private function period(Request $request): array
    {
        $today = CarbonImmutable::today();
        $desde = $this->date($request->query('desde'));
        $hasta = $this->date($request->query('hasta'));

        if ($desde || $hasta) {
            $from = $desde ?? $hasta->startOfMonth();
            $to = $hasta ?? $today;
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $days = $from->diffInDays($to) + 1;
            $previousTo = $from->subDay();

            return [
                'custom' => true, 'mes' => '', 'desde' => $from->toDateString(), 'hasta' => $to->toDateString(),
                'from' => $from, 'to' => $to,
                'previous_from' => $previousTo->subDays($days - 1), 'previous_to' => $previousTo,
                'label' => Ui::date($from).' – '.Ui::date($to),
                'compare_label' => $days === 1 ? 'vs. día anterior' : "vs. {$days} días anteriores",
            ];
        }

        $mes = (string) $request->query('mes', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? CarbonImmutable::createFromFormat('!Y-m', $mes) : $today->startOfMonth();
        $previousMonth = $month->subMonthNoOverflow();

        if ($month->isSameMonth($today)) {
            $elapsed = $month->diffInDays($today);
            $to = $today;
            $previousTo = $previousMonth->addDays($elapsed)->min($previousMonth->endOfMonth());
            $label = $this->monthName($month).' (al '.Ui::date($today).')';
            $compare = 'vs. mismo periodo de '.$this->monthName($previousMonth, false);
        } else {
            $to = $month->endOfMonth();
            $previousTo = $previousMonth->endOfMonth();
            $label = $this->monthName($month);
            $compare = 'vs. '.$this->monthName($previousMonth, false);
        }

        return [
            'custom' => false, 'mes' => $month->format('Y-m'), 'desde' => '', 'hasta' => '',
            'from' => $month, 'to' => $to,
            'previous_from' => $previousMonth, 'previous_to' => $previousTo,
            'label' => $label, 'compare_label' => $compare,
        ];
    }

    /** @return array<string, string> últimos 12 meses: '2026-11' => 'Noviembre 2026' */
    private function monthOptions(): array
    {
        $current = CarbonImmutable::today()->startOfMonth();

        return collect(range(0, 11))
            ->mapWithKeys(fn (int $i) => [$current->subMonthsNoOverflow($i)->format('Y-m') => $this->monthName($current->subMonthsNoOverflow($i))])
            ->all();
    }

    private function monthName(CarbonImmutable $month, bool $capitalize = true): string
    {
        $name = self::MONTHS[$month->month - 1].' '.$month->year;

        return $capitalize ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1) : $name;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        $date = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->toDateString() === $value ? $date : null;
    }
}
