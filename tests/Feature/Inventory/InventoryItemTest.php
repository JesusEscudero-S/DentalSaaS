<?php

namespace Tests\Feature\Inventory;

use App\Enums\ActiveStatus;
use App\Enums\InventoryMovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF 10.1 (alta), edición y RF 10.3 (baja/reactivación) del catálogo de materiales. */
class InventoryItemTest extends TestCase
{
    use InventoryFixtures, RefreshDatabase;

    public function test_lists_items_with_low_stock_alert_and_inventory_value(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $this->item($member, ['name' => 'Guantes nitrilo (M)', 'sku' => 'GNT-NIT-M', 'initial_quantity' => 2, 'min_quantity' => 10, 'unit_cost' => '10.00']);
        $this->item($member, ['name' => 'Eyectores de saliva', 'sku' => 'EYC-SAL-100', 'initial_quantity' => 50, 'min_quantity' => 5, 'unit_cost' => '2.00']);

        $this->actingAsMember($member)->get('/inventario')
            ->assertOk()
            ->assertSee('Guantes nitrilo (M)')
            ->assertSee('Eyectores de saliva')
            ->assertSee('1 insumo en o por debajo del stock mínimo')
            ->assertSee('2 insumos · valor $120.00')
            ->assertViewHas('summary', ['count' => 2, 'value' => '120.00', 'low' => 1]);

        // (Los dos siguen en el select de los modales de entrada/salida; se revisan las filas de la tabla.)
        $rows = fn (string $url) => $this->actingAsMember($member)->get($url)->viewData('items')->pluck('name')->all();
        $this->assertSame(['Eyectores de saliva'], $rows('/inventario?q=eyc-sal'));
        $this->assertSame(['Guantes nitrilo (M)'], $rows('/inventario?stock=bajo'));
    }

    public function test_registers_material_with_initial_stock_lot_and_audit(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $expires = today()->addMonths(6)->toDateString();

        $this->actingAsMember($member)
            ->post('/inventario', [
                'name' => '  Anestesia   Lidocaína 2% ',
                'sku' => 'ans-lid 002',
                'category' => 'Anestésicos',
                'unit_of_measure' => 'cartucho',
                'supplier_name' => 'Dental Depot',
                'initial_quantity' => '15',
                'min_quantity' => '5',
                'unit_cost' => '12.50',
                'location' => 'Estante A-2',
                'lot_code' => 'lid-2611',
                'expires_on' => $expires,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast.title', 'Material registrado exitosamente');

        $item = InventoryItem::sole();
        $this->assertSame(['Anestesia Lidocaína 2%', 'ANS-LID002', ActiveStatus::Active], [$item->name, $item->sku, $item->status]);
        $this->assertSame('Dental Depot', Supplier::sole()->name);

        $stock = $this->stockOf($item);
        $this->assertSame(['15.0000', '5.0000', '12.5000', 'Estante A-2'], [$stock->quantity_on_hand, $stock->min_quantity, $stock->avg_unit_cost, $stock->location]);

        $movement = InventoryMovement::sole();
        $this->assertSame([InventoryMovementType::InitialStock, '15.0000', $member->user_id], [$movement->movement_type, $movement->quantity, $movement->actor_user_id]);

        $lot = InventoryLot::sole();
        $this->assertSame(['LID-2611', $expires, '15.0000', $lot->id], [$lot->lot_code, $lot->expires_on->toDateString(), $lot->quantity_on_hand, $movement->lot_id]);

        $this->assertTrue(AuditLog::where('entity_id', $item->id)->where('action', 'insert')->where('actor_user_id', $member->user_id)->exists());
    }

    public function test_material_without_initial_stock_has_no_movement(): void
    {
        $member = $this->member();
        $this->mainBranch($member);

        $this->actingAsMember($member)
            ->post('/inventario', [
                'name' => 'Fresas diamante', 'sku' => 'FRS-DIA-12', 'category' => 'Instrumental', 'unit_of_measure' => 'paquete',
                'initial_quantity' => '0', 'min_quantity' => '6', 'lot_code' => 'NO-SE-USA', 'expires_on' => '2001-01-01',
            ])
            ->assertSessionHasNoErrors();

        $item = InventoryItem::sole();
        $this->assertSame('0.0000', $this->stockOf($item)->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, InventoryLot::count());
        $this->assertTrue($item->load('stock')->isLowStock()); // 0 ≤ 6: alerta (RN-I8)
    }

    public function test_rejects_duplicates_and_non_integer_quantities(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $this->item($member, ['name' => 'Guantes nitrilo (M)', 'sku' => 'GNT-NIT-M']);

        // RN-I2: el nombre es único (sin distinguir mayúsculas), igual que el SKU.
        $this->actingAsMember($member)
            ->post('/inventario', [
                'name' => 'GUANTES NITRILO (M)', 'sku' => 'gnt-nit-m', 'category' => 'Protección', 'unit_of_measure' => 'caja',
                'initial_quantity' => '1', 'min_quantity' => '1',
            ])
            ->assertSessionHasErrors([
                'name' => 'Ya existe un material con ese nombre',
                'sku' => 'Ya existe un material con ese SKU',
            ], null, 'createItem');

        // RN-I3: enteros ≥ 0. La caducidad requiere el lote.
        $this->actingAsMember($member)
            ->post('/inventario', [
                'name' => 'Algodón', 'sku' => 'ALG-1', 'category' => 'Otra', 'unit_of_measure' => 'kilo',
                'initial_quantity' => '2.5', 'min_quantity' => '-1', 'unit_cost' => '1.999', 'expires_on' => today()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors(['category', 'unit_of_measure', 'initial_quantity', 'min_quantity', 'unit_cost', 'lot_code'], null, 'createItem');

        $this->assertSame(1, InventoryItem::count());
    }

    public function test_same_name_and_sku_are_allowed_in_another_clinic(): void
    {
        $other = $this->member();
        $this->mainBranch($other);
        $this->item($other, ['name' => 'Guantes nitrilo (M)', 'sku' => 'GNT-NIT-M']);

        $member = $this->member();
        $this->mainBranch($member);

        $this->actingAsMember($member)
            ->post('/inventario', [
                'name' => 'Guantes nitrilo (M)', 'sku' => 'GNT-NIT-M', 'category' => 'Protección', 'unit_of_measure' => 'caja',
                'initial_quantity' => '3', 'min_quantity' => '1',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_updates_catalog_data_minimum_and_location_without_touching_stock(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['name' => 'Cemento', 'sku' => 'CMT-1', 'initial_quantity' => 12, 'min_quantity' => 2]);
        $this->item($member, ['name' => 'Resina', 'sku' => 'RES-1']);

        $this->actingAsMember($member)
            ->put(route('inventory.update', $item), [
                'name' => 'Cemento ionómero', 'sku' => 'cmt-ion-15', 'category' => 'Restaurativos', 'unit_of_measure' => 'frasco',
                'supplier_name' => 'GC América', 'min_quantity' => '8', 'location' => 'Estante A-3',
                'initial_quantity' => '999', // se ignora: el stock solo cambia con movimientos
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast.title', 'Material actualizado');

        $item->refresh();
        $this->assertSame(['Cemento ionómero', 'CMT-ION-15', 'GC América'], [$item->name, $item->sku, $item->supplier->name]);
        $stock = $this->stockOf($item);
        $this->assertSame(['12.0000', '8.0000', 'Estante A-3'], [$stock->quantity_on_hand, $stock->min_quantity, $stock->location]);
        $this->assertSame(1, InventoryMovement::where('item_id', $item->id)->count());
        $this->assertTrue(AuditLog::where('entity_id', $item->id)->where('action', 'update')->exists());

        $this->actingAsMember($member)
            ->put(route('inventory.update', $item), [
                'name' => 'resina', 'sku' => 'CMT-ION-15', 'category' => 'Restaurativos', 'unit_of_measure' => 'frasco', 'min_quantity' => '8',
            ])
            ->assertSessionHasErrors(['name' => 'Ya existe un material con ese nombre'], null, 'editItem');
    }

    public function test_deactivates_and_reactivates_material(): void
    {
        $member = $this->member();
        $this->mainBranch($member);
        $item = $this->item($member, ['name' => 'Hilo de sutura 4-0', 'initial_quantity' => 1, 'min_quantity' => 10]);

        $this->actingAsMember($member)
            ->patch(route('inventory.deactivate', $item))
            ->assertSessionHas('toast.title', 'Material dado de baja');
        $this->assertSame(ActiveStatus::Inactive, $item->refresh()->status);
        $this->assertTrue(AuditLog::where('entity_id', $item->id)->whereJsonContains('changed_fields->status', 'inactive')->exists());

        // RN-I10: fuera de la vista operativa y sin alertas, pero visible con el filtro.
        // (Se revisan las filas: el toast "Material dado de baja" de esta respuesta también trae el nombre.)
        $this->actingAsMember($member)->get('/inventario')
            ->assertViewHas('items', fn ($page) => $page->isEmpty())
            ->assertViewHas('summary', fn ($s) => $s['count'] === 0 && $s['low'] === 0);
        $this->actingAsMember($member)->get('/inventario?estado=inactivos')->assertSee('Hilo de sutura 4-0');

        $this->actingAsMember($member)
            ->patch(route('inventory.reactivate', $item))
            ->assertSessionHas('toast.title', 'Material reactivado');
        $this->assertSame(ActiveStatus::Active, $item->refresh()->status);
    }

    public function test_items_of_another_clinic_are_not_accessible(): void
    {
        $other = $this->member();
        $this->mainBranch($other);
        $foreign = $this->item($other, ['name' => 'INSUMO AJENO']);

        $member = $this->member();
        $this->mainBranch($member);

        $this->actingAsMember($member)->get('/inventario')->assertOk()->assertDontSee('INSUMO AJENO');
        $this->actingAsMember($member)->put(route('inventory.update', $foreign), ['name' => 'X'])->assertNotFound();
        $this->actingAsMember($member)->patch(route('inventory.deactivate', $foreign))->assertNotFound();
        $this->assertSame(ActiveStatus::Active, InventoryItem::withoutGlobalScopes()->find($foreign->id)->status);
    }
}
