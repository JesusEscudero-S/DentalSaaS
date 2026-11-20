<?php

namespace Tests\Feature\Finances;

use App\Enums\CashSessionStatus;
use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

/** Registros de Finanzas creados directamente (sin pasar por los servicios bajo prueba). */
trait FinanceFixtures
{
    protected function branch(OrganizationMember $member, array $attributes = []): Branch
    {
        app(CurrentOrganization::class)->set($member);

        return Branch::create(['name' => 'Sucursal '.Str::random(5), ...$attributes]);
    }

    protected function category(OrganizationMember $member, MovementDirection $direction = MovementDirection::Income, array $attributes = []): FinancialCategory
    {
        app(CurrentOrganization::class)->set($member);

        return FinancialCategory::create(['name' => 'Categoría '.Str::random(5), 'direction' => $direction, ...$attributes])->refresh();
    }

    protected function openSession(OrganizationMember $member, Branch $branch): CashSession
    {
        app(CurrentOrganization::class)->set($member);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja '.Str::random(5)]);

        return CashSession::create([
            'cash_register_id' => $register->id,
            'opened_by' => $member->user_id,
            'opened_at' => now(),
            'opening_amount' => '500.00',
        ]);
    }

    protected function closeSession(CashSession $session, OrganizationMember $member): void
    {
        $session->update([
            'status' => CashSessionStatus::Closed,
            'closed_by' => $member->user_id,
            'closed_at' => now(),
            'expected_cash' => '0.00',
            'counted_cash' => '0.00',
            'difference' => '0.00',
        ]);
    }

    protected function movement(OrganizationMember $member, FinancialCategory $category, Branch $branch, array $attributes = []): FinancialMovement
    {
        app(CurrentOrganization::class)->set($member);

        return FinancialMovement::create([
            'branch_id' => $branch->id,
            'direction' => $category->direction,
            'category_id' => $category->id,
            'concept' => 'Movimiento de prueba',
            'occurred_on' => today()->toDateString(),
            'amount' => '100.00',
            'method' => PaymentMethod::Transfer,
            'created_by' => $member->user_id,
            ...$attributes,
        ]);
    }
}
