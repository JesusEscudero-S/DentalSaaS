<?php

namespace Tests\Feature\Finances;

use App\Enums\CashSessionStatus;
use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionAdjustment;
use App\Models\Payment;
use App\Services\Finances\FinancialMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    public function test_opens_a_session(): void
    {
        $member = $this->member();
        $register = CashRegister::create(['branch_id' => $this->branch($member)->id, 'name' => 'Caja 1']);

        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => '$1,500'])
            ->assertSessionHas('toast.title', 'Caja abierta correctamente');

        $session = CashSession::sole();
        $this->assertSame(CashSessionStatus::Open, $session->status);
        $this->assertSame(['1500.00', $member->user_id], [$session->opening_amount, $session->opened_by]);
        $this->assertNotNull($session->opened_at);
        $this->assertTrue(AuditLog::where('entity_id', $session->id)->where('action', 'insert')->exists());
    }

    public function test_opening_amount_can_be_zero_but_not_negative(): void
    {
        $member = $this->member();
        $register = CashRegister::create(['branch_id' => $this->branch($member)->id, 'name' => 'Caja 1']);

        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => '-1'])
            ->assertSessionHasErrors(['opening_amount' => 'El monto de apertura no puede ser negativo.'], null, 'openSession');
        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => ''])
            ->assertSessionHasErrors(['opening_amount'], null, 'openSession');
        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', CashSession::sole()->opening_amount);
    }

    public function test_a_register_cannot_have_two_open_sessions(): void
    {
        $member = $this->member();
        $register = CashRegister::create(['branch_id' => $this->branch($member)->id, 'name' => 'Caja 1']);

        $this->actingAsMember($member)->post(route('finances.cash.open', $register), ['opening_amount' => '100'])->assertSessionHasNoErrors();
        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => '100'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Esta caja ya tiene una sesión abierta']);

        $this->assertSame(1, CashSession::count());
    }

    public function test_an_inactive_register_cannot_be_opened(): void
    {
        $member = $this->member();
        $register = CashRegister::create(['branch_id' => $this->branch($member)->id, 'name' => 'Caja 1', 'status' => 'inactive']);

        $this->actingAsMember($member)
            ->post(route('finances.cash.open', $register), ['opening_amount' => '100'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'No se puede abrir una caja inactiva.']);
        $this->assertSame(0, CashSession::count());
    }

    public function test_cut_computes_expected_cash_excluding_reversed_movements(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $income = $this->category($member);
        $expense = $this->category($member, MovementDirection::Expense);
        $session = $this->openSession($member, $branch);
        $cash = ['method' => PaymentMethod::Cash, 'cash_session_id' => $session->id];

        $reversed = $this->movement($member, $income, $branch, [...$cash, 'amount' => '1000.00']);
        $this->movement($member, $income, $branch, [...$cash, 'amount' => '1000.00', 'reversed_of_id' => $reversed->id]);
        $this->movement($member, $income, $branch, [...$cash, 'amount' => '300.00']);
        $this->movement($member, $expense, $branch, [...$cash, 'amount' => '120.00']);
        $this->movement($member, $income, $branch, ['amount' => '999.00']); // transferencia: no es efectivo
        $otherSession = $this->openSession($member, $branch);
        $this->movement($member, $income, $branch, ['method' => PaymentMethod::Cash, 'cash_session_id' => $otherSession->id, 'amount' => '777.00']);
        app(FinancialMovementService::class)->recordFromPayment(Payment::create([
            'branch_id' => $branch->id,
            'patient_id' => $this->createPatient($member)->id,
            'paid_at' => now(),
            'amount' => '850.00',
            'method' => PaymentMethod::Cash,
            'idempotency_key' => 'pago-efectivo',
            'cash_session_id' => $session->id,
            'created_by' => $member->user_id,
        ]));

        $this->actingAsMember($member)->get(route('finances.cash.sessions.show', $session))
            ->assertOk()
            ->assertViewHas('summary', ['opening' => '500.00', 'cash_in' => '1150.00', 'cash_out' => '120.00', 'expected' => '1530.00']);

        $this->actingAsMember($member)
            ->post(route('finances.cash.close', $session), ['counted_cash' => '1,530.00'])
            ->assertRedirect(route('finances.cash.sessions.show', $session))
            ->assertSessionHas('toast.title', 'Corte de caja realizado');

        $session->refresh();
        $this->assertSame(CashSessionStatus::Closed, $session->status);
        $this->assertSame(['1530.00', '1530.00', '0.00'], [$session->expected_cash, $session->counted_cash, $session->difference]);
        $this->assertSame($member->user_id, $session->closed_by);
        $this->assertNotNull($session->closed_at);
        $this->assertNull($session->notes);
        $this->assertTrue(AuditLog::where('entity_id', $session->id)->where('action', 'update')->exists());
    }

    public function test_a_difference_requires_notes(): void
    {
        $member = $this->member();
        $session = $this->openSession($member, $this->branch($member));
        $url = route('finances.cash.close', $session);

        $this->actingAsMember($member)
            ->post($url, ['counted_cash' => '-1'])
            ->assertSessionHasErrors(['counted_cash' => 'El efectivo contado no puede ser negativo.'], null, 'closeSession');
        $this->actingAsMember($member)
            ->post($url, ['counted_cash' => '450', 'notes' => '   '])
            ->assertSessionHasErrors(['notes' => 'Explica la diferencia entre el efectivo contado y el esperado.'], null, 'closeSession');
        $this->assertTrue($session->refresh()->isOpen());

        $this->actingAsMember($member)
            ->post($url, ['counted_cash' => '450', 'notes' => 'Se dio cambio de más'])
            ->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertSame(['500.00', '450.00', '-50.00', 'Se dio cambio de más'], [$session->expected_cash, $session->counted_cash, $session->difference, $session->notes]);
    }

    public function test_a_closed_session_cannot_be_cut_again_nor_receive_cash(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $session = $this->openSession($member, $branch);

        $this->actingAsMember($member)->post(route('finances.cash.close', $session), ['counted_cash' => '500'])->assertSessionHasNoErrors();
        $this->actingAsMember($member)
            ->post(route('finances.cash.close', $session), ['counted_cash' => '500'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Esta caja ya fue cortada.']);

        $income = $this->category($member);
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', [
                'direction' => 'ingreso', 'category_id' => $income->id, 'concept' => 'Cobro tardío', 'occurred_on' => today()->toDateString(),
                'amount' => '100', 'method' => 'efectivo', 'branch_id' => $branch->id, 'cash_session_id' => $session->id,
            ])
            ->assertSessionHasErrors(['cash_session_id' => 'Abre una caja en esta sucursal para registrar movimientos en efectivo'], null, 'createMovement');
    }

    public function test_adjustments_are_only_allowed_on_closed_sessions(): void
    {
        $member = $this->member();
        $session = $this->openSession($member, $this->branch($member));
        $url = route('finances.cash.adjustments.store', $session);

        $this->actingAsMember($member)
            ->post($url, ['amount' => '10', 'reason' => 'Prueba de ajuste'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Los ajustes solo se registran en cajas ya cortadas.']);

        $this->closeSession($session, $member);

        $this->actingAsMember($member)
            ->post($url, ['amount' => '0', 'reason' => 'abc'])
            ->assertSessionHasErrors(['amount' => 'El monto no puede ser 0.', 'reason'], null, 'createAdjustment');

        $this->actingAsMember($member)
            ->post($url, ['amount' => '-25.50', 'reason' => '  Billete   falso detectado '])
            ->assertSessionHas('toast.title', 'Ajuste registrado');

        $adjustment = CashSessionAdjustment::sole();
        $this->assertSame(['-25.50', 'Billete falso detectado', $member->user_id], [$adjustment->amount, $adjustment->reason, $adjustment->created_by]);
        $this->assertTrue(AuditLog::where('entity_id', $adjustment->id)->where('action', 'insert')->exists());

        $this->actingAsMember($member)->get(route('finances.cash.sessions.show', $session))
            ->assertOk()
            ->assertSee('Billete falso detectado')
            ->assertSee('−$25.50');
    }

    public function test_sessions_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->openSession($other, $this->branch($other));

        $member = $this->member();
        $this->actingAsMember($member)->get(route('finances.cash.sessions.show', $foreign))->assertNotFound();
        $this->actingAsMember($member)->post(route('finances.cash.close', $foreign), ['counted_cash' => '0'])->assertNotFound();
        $this->actingAsMember($member)->post(route('finances.cash.adjustments.store', $foreign), ['amount' => '1', 'reason' => 'Intento ajeno'])->assertNotFound();
        $this->assertTrue(CashSession::withoutGlobalScopes()->find($foreign->id)->isOpen());
    }
}
