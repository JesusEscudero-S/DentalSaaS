<?php

namespace Tests\Feature\Finances;

use App\Enums\ActiveStatus;
use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashSessionAdjustment;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    public function test_cash_page_shows_registers_expected_cash_and_cut_history(): void
    {
        $member = $this->member();
        $branch = $this->branch($member, ['name' => 'Matriz', 'is_main' => true]);
        $session = $this->openSession($member, $branch);
        $cash = ['method' => PaymentMethod::Cash, 'cash_session_id' => $session->id];
        $this->movement($member, $this->category($member), $branch, [...$cash, 'amount' => '250.00']);
        $this->movement($member, $this->category($member, MovementDirection::Expense), $branch, [...$cash, 'amount' => '100.00']);

        $closed = $this->openSession($member, $branch);
        $this->closeSession($closed, $member);
        CashSessionAdjustment::create(['cash_session_id' => $closed->id, 'amount' => '-20.00', 'reason' => 'Faltante', 'created_by' => $member->user_id]);

        $this->actingAsMember($member)->get('/finanzas/caja')
            ->assertOk()
            ->assertSee($session->register->name)
            ->assertSee('Matriz')
            ->assertViewHas('expected', fn ($expected) => $expected[$session->id] === '650.00')
            ->assertSee('$650.00')
            ->assertViewHas('history', fn ($page) => $page->total() === 1 && $page->first()->adjustments_sum_amount === '-20.00');
    }

    public function test_creates_register_and_rejects_duplicate_names_per_branch(): void
    {
        $member = $this->member();
        $branch = $this->branch($member, ['is_main' => true]);
        $otherBranch = $this->branch($member);
        $inactiveBranch = $this->branch($member, ['status' => 'inactive']);

        $this->actingAsMember($member)
            ->post('/finanzas/caja/cajas', ['name' => '  Caja   1 ', 'branch_id' => $branch->id])
            ->assertSessionHas('toast.title', 'Caja creada');

        $register = CashRegister::sole();
        $this->assertSame(['Caja 1', $branch->id, ActiveStatus::Active], [$register->name, $register->branch_id, $register->status]);
        $this->assertTrue(AuditLog::where('entity_id', $register->id)->where('action', 'insert')->exists());

        $this->actingAsMember($member)
            ->post('/finanzas/caja/cajas', ['name' => 'caja 1', 'branch_id' => $branch->id])
            ->assertSessionHasErrors(['name' => 'Ya existe una caja con ese nombre en esta sucursal.'], null, 'createRegister');
        $this->actingAsMember($member)
            ->post('/finanzas/caja/cajas', ['name' => 'Caja 1', 'branch_id' => $otherBranch->id])
            ->assertSessionHasNoErrors();
        $this->actingAsMember($member)
            ->post('/finanzas/caja/cajas', ['name' => 'Caja 2', 'branch_id' => $inactiveBranch->id])
            ->assertSessionHasErrors(['branch_id' => 'La sucursal seleccionada no es válida.'], null, 'createRegister');
        $this->actingAsMember($member)
            ->post('/finanzas/caja/cajas', ['name' => 'X', 'branch_id' => $branch->id])
            ->assertSessionHasErrors(['name'], null, 'createRegister');

        $this->assertSame(2, CashRegister::count());
    }

    public function test_renames_register_without_moving_it_to_another_branch(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $register = $this->openSession($member, $branch)->register;
        $sibling = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja 2']);
        $otherBranch = $this->branch($member);

        $this->actingAsMember($member)
            ->put(route('finances.cash.registers.update', $register), ['name' => 'Caja principal', 'branch_id' => $otherBranch->id])
            ->assertSessionHas('toast.title', 'Caja actualizada');
        $register->refresh();
        $this->assertSame(['Caja principal', $branch->id], [$register->name, $register->branch_id]);
        $this->assertTrue(AuditLog::where('entity_id', $register->id)->where('action', 'update')->exists());

        $this->actingAsMember($member)
            ->put(route('finances.cash.registers.update', $register), ['name' => $sibling->name])
            ->assertSessionHasErrors(['name' => 'Ya existe una caja con ese nombre en esta sucursal.'], null, 'editRegister');
    }

    public function test_cannot_deactivate_a_register_with_an_open_session(): void
    {
        $member = $this->member();
        $session = $this->openSession($member, $this->branch($member));
        $register = $session->register;

        $this->actingAsMember($member)
            ->patch(route('finances.cash.registers.deactivate', $register))
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'No se puede desactivar una caja con una sesión abierta; haz el corte primero.']);
        $this->assertSame(ActiveStatus::Active, $register->refresh()->status);

        $this->closeSession($session, $member);
        $this->actingAsMember($member)
            ->patch(route('finances.cash.registers.deactivate', $register))
            ->assertSessionHas('toast.title', 'Caja desactivada');
        $this->assertSame(ActiveStatus::Inactive, $register->refresh()->status);
        $this->assertTrue(AuditLog::where('entity_id', $register->id)->whereJsonContains('changed_fields->status', 'inactive')->exists());

        $this->actingAsMember($member)
            ->patch(route('finances.cash.registers.reactivate', $register))
            ->assertSessionHas('toast.title', 'Caja reactivada');
        $this->assertSame(ActiveStatus::Active, $register->refresh()->status);
    }

    public function test_registers_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->openSession($other, $this->branch($other))->register;
        $this->closeSession($foreign->openSession, $other);

        $member = $this->member();
        $this->actingAsMember($member)->get('/finanzas/caja')->assertOk()->assertDontSee($foreign->name);
        $this->actingAsMember($member)->put(route('finances.cash.registers.update', $foreign), ['name' => 'Mía'])->assertNotFound();
        $this->actingAsMember($member)->patch(route('finances.cash.registers.deactivate', $foreign))->assertNotFound();
        $this->actingAsMember($member)->post(route('finances.cash.open', $foreign), ['opening_amount' => '100'])->assertNotFound();

        app(CurrentOrganization::class)->set($other);
        $this->assertSame(1, $foreign->sessions()->count());
    }
}
