<?php

namespace Tests\Feature\Finances;

use App\Enums\ActiveStatus;
use App\Enums\CashSessionStatus;
use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionAdjustment;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceModelsTest extends TestCase
{
    use RefreshDatabase;

    /** Crea un juego completo de registros de Finanzas en la clínica del miembro. */
    private function seedFinance(OrganizationMember $member): FinancialMovement
    {
        app(CurrentOrganization::class)->set($member);

        $branch = Branch::create(['name' => 'Matriz', 'is_main' => true]);
        $category = FinancialCategory::create(['name' => 'Renta', 'direction' => MovementDirection::Expense]);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja 1']);
        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'opened_by' => $member->user_id,
            'opened_at' => now(),
            'opening_amount' => '500.00',
        ]);

        return FinancialMovement::create([
            'branch_id' => $branch->id,
            'direction' => MovementDirection::Expense,
            'category_id' => $category->id,
            'concept' => 'Renta del local',
            'occurred_on' => today(),
            'amount' => '1200.50',
            'method' => PaymentMethod::Cash,
            'cash_session_id' => $session->id,
            'created_by' => $member->user_id,
        ]);
    }

    public function test_finance_models_are_isolated_by_organization(): void
    {
        $member = $this->member();
        $other = $this->member();

        $mine = $this->seedFinance($member);
        $this->seedFinance($other);

        app(CurrentOrganization::class)->set($member);

        foreach ([Branch::class, FinancialCategory::class, CashRegister::class, CashSession::class, FinancialMovement::class] as $model) {
            $this->assertSame(1, $model::count(), $model);
            $this->assertSame($member->organization_id, $model::sole()->organization_id, $model);
        }

        $this->assertNull(FinancialMovement::find($this->otherMovementId($other)));
        $this->assertSame($mine->id, FinancialMovement::sole()->id);
    }

    public function test_casts_and_relations(): void
    {
        $member = $this->member();
        $movement = $this->seedFinance($member)->fresh(['category', 'branch', 'cashSession.register', 'creator']);

        $this->assertSame('1200.50', $movement->amount);
        $this->assertSame(MovementDirection::Expense, $movement->direction);
        $this->assertSame(PaymentMethod::Cash, $movement->method);
        $this->assertSame('Renta', $movement->category->name);
        $this->assertSame(ActiveStatus::Active, $movement->category->status);
        $this->assertSame('Matriz', $movement->branch->name);
        $this->assertSame(CashSessionStatus::Open, $movement->cashSession->status);
        $this->assertSame('500.00', $movement->cashSession->opening_amount);
        $this->assertSame($movement->cashSession->id, $movement->cashSession->register->openSession->id);
        $this->assertSame($member->user_id, $movement->creator->id);
    }

    public function test_effective_scope_excludes_reversed_pairs(): void
    {
        $member = $this->member();
        $original = $this->seedFinance($member);

        $kept = $original->replicate()->fill(['concept' => 'Otro egreso']);
        $kept->save();
        $reversal = $original->replicate()->fill(['concept' => 'Reversión: error', 'reversed_of_id' => $original->id]);
        $reversal->save();

        $this->assertSame([$kept->id], FinancialMovement::effective()->pluck('id')->all());
        $this->assertTrue($reversal->isReversal());
        $this->assertSame($reversal->id, $original->reversal->id);
    }

    public function test_closed_session_is_immutable_but_accepts_adjustments(): void
    {
        $member = $this->member();
        $session = $this->seedFinance($member)->cashSession;

        $session->update([
            'status' => CashSessionStatus::Closed,
            'closed_by' => $member->user_id,
            'closed_at' => now(),
            'expected_cash' => '-700.50',
            'counted_cash' => '0.00',
            'difference' => '700.50',
            'notes' => 'Prueba',
        ]);

        try {
            $session->update(['notes' => 'Cambio']);
            $this->fail('El trigger debió bloquear la actualización.');
        } catch (QueryException $e) {
            $this->assertSame('45000', $e->errorInfo[0]);
        }

        CashSessionAdjustment::create([
            'cash_session_id' => $session->id,
            'amount' => '-10.00',
            'reason' => 'Faltante',
            'created_by' => $member->user_id,
        ]);
        $this->assertSame('-10.00', $session->adjustments()->sole()->amount);
    }

    private function otherMovementId(OrganizationMember $other): string
    {
        return FinancialMovement::withoutGlobalScopes()->where('organization_id', $other->organization_id)->value('id');
    }
}
