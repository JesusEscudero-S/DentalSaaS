<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF 10.2: entradas y salidas (RN-I5 a RN-I8) e historial de movimientos. */
class InventoryMovementTest extends TestCase
{
    use InventoryFixtures, RefreshDatabase;

    public function test_entry_adds_stock_updates_average_cost_and_lot(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['initial_quantity' => 10, 'unit_cost' => '10.00']);
        $expires = today()->addMonths(3)->toDateString();

        $this->actingAsMember($member)
            ->post('/inventario/entradas', [
                'item_id' => $item->id, 'quantity' => '10', 'unit_cost' => '20.00', 'reference' => 'Factura MS-4471',
                'lot_code' => 'l-1', 'expires_on' => $expires,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast.title', 'Entrada registrada');

        $stock = $this->stockOf($item);
        $this->assertSame(['20.0000', '15.0000'], [$stock->quantity_on_hand, $stock->avg_unit_cost]); // promedio ponderado (trigger)

        $movement = InventoryMovement::where('movement_type', InventoryMovementType::Entry->value)->sole();
        $this->assertSame(['10.0000', 'Factura MS-4471', $member->user_id], [$movement->quantity, $movement->reference, $movement->actor_user_id]);
        $this->assertSame(['L-1', '10.0000', $expires], [$movement->lot->lot_code, $movement->lot->quantity_on_hand, $movement->lot->expires_on->toDateString()]);
    }

    public function test_exit_subtracts_stock_and_warns_when_reaching_the_minimum(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['name' => 'Guantes', 'initial_quantity' => 12, 'min_quantity' => 5]);

        $this->actingAsMember($member)
            ->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '2', 'reference' => 'Consulta'])
            ->assertSessionHas('toast.type', 'success')
            ->assertSessionHas('toast.title', 'Salida registrada');

        // RN-I8: queda en el mínimo (5 ≤ 5) → aviso de stock bajo.
        $this->actingAsMember($member)
            ->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '5'])
            ->assertSessionHas('toast.type', 'info')
            ->assertSessionHas('toast.title', 'Salida registrada · stock bajo');

        $this->assertSame('5.0000', $this->stockOf($item)->quantity_on_hand);
        $this->assertSame(['-2.0000', '-5.0000'], InventoryMovement::where('movement_type', 'salida')->orderBy('quantity', 'desc')->pluck('quantity')->all());
        $this->assertTrue($item->load('stock')->isLowStock());
    }

    public function test_exit_larger_than_stock_is_blocked(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['initial_quantity' => 3]);

        // RN-I6: la salida nunca supera el stock actual.
        $this->actingAsMember($member)
            ->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '5'])
            ->assertSessionHas('toast.type', 'error')
            ->assertSessionHas('toast.title', 'Stock insuficiente');

        $this->assertSame('3.0000', $this->stockOf($item)->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::where('movement_type', 'salida')->count());
    }

    public function test_exit_consumes_the_lots_that_expire_first(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['initial_quantity' => 0]);

        $this->actingAsMember($member)->post('/inventario/entradas', [
            'item_id' => $item->id, 'quantity' => '5', 'lot_code' => 'TARDE', 'expires_on' => today()->addDays(60)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->actingAsMember($member)->post('/inventario/entradas', [
            'item_id' => $item->id, 'quantity' => '5', 'lot_code' => 'PRONTO', 'expires_on' => today()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAsMember($member)->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '7'])->assertSessionHas('toast.title', 'Salida registrada');

        $lots = InventoryLot::withoutGlobalScopes()->pluck('quantity_on_hand', 'lot_code');
        $this->assertSame(['PRONTO' => '0.0000', 'TARDE' => '3.0000'], $lots->sortKeys()->all());
        $this->assertSame('3.0000', $this->stockOf($item)->quantity_on_hand);
    }

    public function test_inactive_material_does_not_accept_movements(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['initial_quantity' => 5]);
        $this->actingAsMember($member)->patch(route('inventory.deactivate', $item));

        // RN-I10
        $this->actingAsMember($member)
            ->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '1'])
            ->assertSessionHasErrors(['item_id' => 'El material está dado de baja; reactívalo para registrar movimientos.'], null, 'exitMovement');
        $this->actingAsMember($member)
            ->post('/inventario/entradas', ['item_id' => $item->id, 'quantity' => '1'])
            ->assertSessionHasErrors(['item_id'], null, 'entryMovement');

        $this->assertSame('5.0000', $this->stockOf($item)->quantity_on_hand);
    }

    public function test_validates_quantity_and_rejects_items_of_another_clinic(): void
    {
        $other = $this->member();
        $this->mainBranch($other);
        $foreign = $this->item($other);

        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member);

        foreach (['0', '1.5', ''] as $quantity) {
            $this->actingAsMember($member)
                ->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => $quantity])
                ->assertSessionHasErrors(['quantity'], null, 'exitMovement');
        }

        $this->actingAsMember($member)
            ->post('/inventario/entradas', ['item_id' => $foreign->id, 'quantity' => '3'])
            ->assertSessionHasErrors(['item_id' => 'Selecciona un insumo de la lista.'], null, 'entryMovement');

        $this->assertSame('10.0000', $this->stockOf($foreign)->quantity_on_hand);
    }

    public function test_lists_movement_history_and_filters_by_type(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['name' => 'Eyectores de saliva', 'initial_quantity' => 10]);
        $this->actingAsMember($member)->post('/inventario/salidas', ['item_id' => $item->id, 'quantity' => '4', 'reference' => 'Consumo semanal']);

        $this->actingAsMember($member)->get('/inventario/movimientos')
            ->assertOk()
            ->assertSee('Eyectores de saliva')
            ->assertSee('Inventario inicial')
            ->assertSee('Consumo semanal')
            ->assertViewHas('movements', fn ($page) => $page->total() === 2);

        $this->actingAsMember($member)->get('/inventario/movimientos?tipo=salida')
            ->assertViewHas('movements', fn ($page) => $page->total() === 1 && $page->first()->movement_type === InventoryMovementType::Exit);
    }

    public function test_movements_are_immutable(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $this->item($member, ['initial_quantity' => 4]);

        $this->expectException(QueryException::class); // trigger inventory_movements_bu (SQLSTATE 45000)
        InventoryMovement::withoutGlobalScopes()->sole()->update(['reference' => 'Cambio']);
    }
}
