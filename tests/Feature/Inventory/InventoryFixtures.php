<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\OrganizationMember;
use App\Services\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

/** Sucursal e insumos creados con el servicio real (el stock lo calcula el trigger). */
trait InventoryFixtures
{
    protected function mainBranch(OrganizationMember $member): Branch
    {
        app(CurrentOrganization::class)->set($member);

        return Branch::create(['name' => 'Matriz '.Str::random(4), 'is_main' => true]);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function item(OrganizationMember $member, array $overrides = []): InventoryItem
    {
        app(CurrentOrganization::class)->set($member);

        return app(InventoryService::class)->create([
            'name' => 'Insumo '.Str::random(6),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'category' => 'Consumibles',
            'unit_of_measure' => 'caja',
            'supplier_name' => null,
            'initial_quantity' => 10,
            'min_quantity' => 2,
            'unit_cost' => '10.00',
            'location' => null,
            ...$overrides,
        ]);
    }

    protected function stockOf(InventoryItem $item): InventoryStock
    {
        return InventoryStock::withoutGlobalScopes()->where('item_id', $item->id)->sole();
    }
}
