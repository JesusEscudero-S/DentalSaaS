<?php

namespace Tests\Feature\Finances;

use App\Enums\ActiveStatus;
use App\Enums\MovementDirection;
use App\Models\AuditLog;
use App\Models\FinancialCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialCategoryTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    public function test_lists_categories_with_their_movement_count(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $rent = $this->category($member, MovementDirection::Expense, ['name' => 'Renta']);
        $this->category($member, MovementDirection::Income, ['name' => 'Otros ingresos']);
        $this->movement($member, $rent, $branch);
        $this->movement($member, $rent, $branch);

        $this->actingAsMember($member)->get('/finanzas/categorias')
            ->assertOk()
            ->assertSee('Renta')
            ->assertSee('Otros ingresos')
            ->assertViewHas('categories', fn ($page) => $page->firstWhere('id', $rent->id)->movements_count === 2);

        $this->actingAsMember($member)->get('/finanzas/categorias?tipo=ingreso')->assertSee('Otros ingresos')->assertDontSee('Renta');
    }

    public function test_creates_category_and_rejects_duplicates_per_direction(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)
            ->post('/finanzas/categorias', ['name' => '  Material   dental ', 'direction' => 'egreso'])
            ->assertSessionHas('toast.title', 'Categoría creada');

        $category = FinancialCategory::sole();
        $this->assertSame('Material dental', $category->name);
        $this->assertSame(ActiveStatus::Active, $category->status);
        $this->assertTrue(AuditLog::where('entity_id', $category->id)->where('action', 'insert')->exists());

        $this->actingAsMember($member)
            ->post('/finanzas/categorias', ['name' => 'MATERIAL DENTAL', 'direction' => 'egreso'])
            ->assertSessionHasErrors(['name' => 'Ya existe una categoría con ese nombre'], null, 'createCategory');

        $this->actingAsMember($member)
            ->post('/finanzas/categorias', ['name' => 'Material dental', 'direction' => 'ingreso'])
            ->assertSessionHasNoErrors();

        $this->actingAsMember($member)
            ->post('/finanzas/categorias', ['name' => 'A', 'direction' => 'otro'])
            ->assertSessionHasErrors(['name', 'direction'], null, 'createCategory');

        $this->assertSame(2, FinancialCategory::count());
    }

    public function test_same_name_is_allowed_in_another_organization(): void
    {
        $other = $this->member();
        $this->category($other, MovementDirection::Expense, ['name' => 'Renta']);

        $this->actingAsMember($this->member())
            ->post('/finanzas/categorias', ['name' => 'Renta', 'direction' => 'egreso'])
            ->assertSessionHasNoErrors();
    }

    public function test_updates_category_and_locks_direction_once_it_has_movements(): void
    {
        $member = $this->member();
        $category = $this->category($member, MovementDirection::Expense, ['name' => 'Renta']);
        $this->category($member, MovementDirection::Income, ['name' => 'Consultas']);
        $url = route('finances.categories.update', $category);

        $this->actingAsMember($member)
            ->put($url, ['name' => 'Renta del local', 'direction' => 'ingreso'])
            ->assertSessionHas('toast.title', 'Categoría actualizada');
        $category->refresh();
        $this->assertSame(['Renta del local', MovementDirection::Income], [$category->name, $category->direction]);
        $this->assertTrue(AuditLog::where('entity_id', $category->id)->where('action', 'update')->exists());

        $this->actingAsMember($member)
            ->put($url, ['name' => 'Consultas', 'direction' => 'ingreso'])
            ->assertSessionHasErrors(['name' => 'Ya existe una categoría con ese nombre'], null, 'editCategory');

        $this->movement($member, $category, $this->branch($member));
        $this->actingAsMember($member)
            ->put($url, ['name' => 'Renta del local', 'direction' => 'egreso'])
            ->assertSessionHasErrors(['direction' => 'No se puede cambiar el tipo de una categoría que ya tiene movimientos.'], null, 'editCategory');
        $this->assertSame(MovementDirection::Income, $category->refresh()->direction);

        // El nombre sí se puede cambiar aunque tenga movimientos.
        $this->actingAsMember($member)->put($url, ['name' => 'Rentas', 'direction' => 'ingreso'])->assertSessionHasNoErrors();
        $this->assertSame('Rentas', $category->refresh()->name);
    }

    public function test_deactivates_and_reactivates_and_inactive_is_hidden_from_new_movements(): void
    {
        $member = $this->member();
        $this->branch($member, ['is_main' => true]);
        $category = $this->category($member, MovementDirection::Income, ['name' => 'Donativos']);
        $inCreateModal = Str::between((string) Js::from(['name' => 'Donativos']), '{', '}'); // como la escribe @js() en el x-data del modal

        $this->actingAsMember($member)->get('/finanzas/movimientos')->assertSee($inCreateModal, false);

        $this->actingAsMember($member)
            ->patch(route('finances.categories.deactivate', $category))
            ->assertSessionHas('toast.title', 'Categoría desactivada');
        $this->assertSame(ActiveStatus::Inactive, $category->refresh()->status);
        $this->assertTrue(AuditLog::where('entity_id', $category->id)->whereJsonContains('changed_fields->status', 'inactive')->exists());

        // Sigue en el filtro (marcada como inactiva) pero no en el modal de alta.
        $this->actingAsMember($member)->get('/finanzas/movimientos')
            ->assertSee('Donativos · Ingreso (inactiva)')
            ->assertDontSee($inCreateModal, false);

        $this->actingAsMember($member)
            ->patch(route('finances.categories.reactivate', $category))
            ->assertSessionHas('toast.title', 'Categoría reactivada');
        $this->assertSame(ActiveStatus::Active, $category->refresh()->status);
    }

    public function test_categories_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->category($other, MovementDirection::Expense, ['name' => 'CATEGORIA AJENA']);

        $member = $this->member();
        $this->actingAsMember($member)->get('/finanzas/categorias')->assertOk()->assertDontSee('CATEGORIA AJENA');
        $this->actingAsMember($member)->put(route('finances.categories.update', $foreign), ['name' => 'X', 'direction' => 'egreso'])->assertNotFound();
        $this->actingAsMember($member)->patch(route('finances.categories.deactivate', $foreign))->assertNotFound();
        $this->assertSame(ActiveStatus::Active, FinancialCategory::withoutGlobalScopes()->find($foreign->id)->status);
    }
}
