<?php

namespace Tests\Feature\Finances;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\FinancialCategory;
use App\Models\OrganizationMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private OrganizationMember $member;

    private Branch $main;

    private Branch $north;

    private FinancialCategory $consultations;

    private FinancialCategory $rent;

    private CarbonImmutable $month;

    /**
     * Mes analizado (hace 2 meses) y el anterior:
     *   mes:      ingresos 1000 (Matriz) + 500 (Norte), egreso 300 (Matriz), un ingreso de 999 revertido.
     *   anterior: ingreso 800, egreso 100 (Matriz).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->member();
        $this->main = $this->branch($this->member, ['name' => 'Matriz', 'is_main' => true]);
        $this->north = $this->branch($this->member, ['name' => 'Norte']);
        $this->consultations = $this->category($this->member, MovementDirection::Income, ['name' => 'Consultas']);
        $this->rent = $this->category($this->member, MovementDirection::Expense, ['name' => 'Renta']);
        $this->month = CarbonImmutable::today()->startOfMonth()->subMonthsNoOverflow(2);

        $on = fn (CarbonImmutable $date) => ['occurred_on' => $date->toDateString()];
        $this->movement($this->member, $this->consultations, $this->main, ['amount' => '1000.00', 'concept' => 'Ortodoncia', ...$on($this->month->addDays(4))]);
        $this->movement($this->member, $this->consultations, $this->north, ['amount' => '500.00', 'concept' => 'Limpieza', ...$on($this->month->addDays(9))]);
        $this->movement($this->member, $this->rent, $this->main, ['amount' => '300.00', 'concept' => 'Renta local', ...$on($this->month)]);
        $reversed = $this->movement($this->member, $this->consultations, $this->main, ['amount' => '999.00', 'concept' => 'Cobro duplicado', ...$on($this->month->addDays(2))]);
        $this->movement($this->member, $this->consultations, $this->main, ['amount' => '999.00', 'concept' => 'Reversión', 'reversed_of_id' => $reversed->id, ...$on($this->month->addDays(3))]);

        $previous = $this->month->subMonthNoOverflow();
        $this->movement($this->member, $this->consultations, $this->main, ['amount' => '800.00', ...$on($previous->addDays(5))]);
        $this->movement($this->member, $this->rent, $this->main, ['amount' => '100.00', ...$on($previous)]);
    }

    public function test_kpis_sum_the_period_and_compare_with_the_previous_month(): void
    {
        $this->actingAsMember($this->member)
            ->get('/finanzas?mes='.$this->month->format('Y-m'))
            ->assertOk()
            ->assertViewHas('current', ['income' => '1500.00', 'expense' => '300.00', 'net' => '1200.00'])
            ->assertViewHas('changes', ['income' => '87.5', 'expense' => '200.0', 'net' => '71.4'])
            ->assertViewHas('period', fn ($p) => $p['from']->equalTo($this->month) && $p['to']->equalTo($this->month->endOfMonth()))
            ->assertSee('Ingresos vs. egresos');
    }

    public function test_branch_filter_applies_to_every_figure(): void
    {
        $this->actingAsMember($this->member)
            ->get('/finanzas?mes='.$this->month->format('Y-m').'&sucursal='.$this->main->id)
            ->assertViewHas('current', ['income' => '1000.00', 'expense' => '300.00', 'net' => '700.00'])
            ->assertViewHas('changes', fn ($c) => $c['income'] === '25.0')
            ->assertViewHas('incomeByCategory', fn ($b) => $b['total'] === '1000.00')
            ->assertViewHas('latest', fn ($latest) => ! $latest->pluck('concept')->contains('Limpieza'));
    }

    public function test_six_month_series_by_category_and_latest_movements(): void
    {
        $response = $this->actingAsMember($this->member)->get('/finanzas?mes='.$this->month->format('Y-m'));

        $response->assertViewHas('series', function (array $series) {
            $byMonth = collect($series)->keyBy('month');

            return count($series) === 6
                && $series[5]['month'] === $this->month->format('Y-m')
                && $byMonth[$this->month->format('Y-m')]['income'] === '1500.00'
                && $byMonth[$this->month->subMonthNoOverflow()->format('Y-m')]['expense'] === '100.00'
                && $series[0]['income'] === '0.00';
        });

        $response->assertViewHas('incomeByCategory', ['total' => '1500.00', 'rows' => [['name' => 'Consultas', 'total' => '1500.00', 'share' => '100.0']]]);
        $response->assertViewHas('expenseByCategory', ['total' => '300.00', 'rows' => [['name' => 'Renta', 'total' => '300.00', 'share' => '100.0']]]);
        $response->assertViewHas('latest', fn ($latest) => $latest->pluck('concept')->all() === ['Limpieza', 'Ortodoncia', 'Renta local']);
    }

    public function test_custom_range_compares_with_the_same_number_of_previous_days(): void
    {
        $from = $this->month->addDays(4);
        $to = $this->month->addDays(9);

        $this->actingAsMember($this->member)
            ->get('/finanzas?desde='.$from->toDateString().'&hasta='.$to->toDateString())
            ->assertViewHas('current', ['income' => '1500.00', 'expense' => '0.00', 'net' => '1500.00'])
            ->assertViewHas('period', fn ($p) => $p['custom']
                && $p['previous_to']->equalTo($from->subDay())
                && $p['previous_from']->equalTo($from->subDays(6)));
    }

    public function test_current_month_is_compared_with_the_same_days_of_the_previous_month(): void
    {
        $this->actingAsMember($this->member)
            ->get('/finanzas')
            ->assertViewHas('period', function ($p) {
                $today = CarbonImmutable::today();

                return $p['to']->equalTo($today)
                    && $p['previous_from']->equalTo($today->startOfMonth()->subMonthNoOverflow())
                    && $p['previous_to']->lte($p['previous_from']->endOfMonth());
            });
    }

    public function test_open_cash_kpi_counts_open_sessions_by_branch(): void
    {
        $this->openSession($this->member, $this->main);
        $this->openSession($this->member, $this->north)->update(['opening_amount' => '200.00']);
        $closed = $this->openSession($this->member, $this->main);
        $this->closeSession($closed, $this->member);
        $this->movement($this->member, $this->consultations, $this->main, ['method' => PaymentMethod::Cash, 'cash_session_id' => $closed->id]);

        $this->actingAsMember($this->member)->get('/finanzas')
            ->assertViewHas('openCash', ['count' => 2, 'opening' => '700.00']);
        $this->actingAsMember($this->member)->get('/finanzas?sucursal='.$this->main->id)
            ->assertViewHas('openCash', ['count' => 1, 'opening' => '500.00']);
    }

    public function test_other_organizations_are_not_included(): void
    {
        $other = $this->member();
        $this->movement($other, $this->category($other), $this->branch($other), ['amount' => '5000.00', 'occurred_on' => $this->month->addDays(4)->toDateString()]);

        $this->actingAsMember($this->member)
            ->get('/finanzas?mes='.$this->month->format('Y-m'))
            ->assertViewHas('current', fn ($c) => $c['income'] === '1500.00');
    }
}
