<?php

namespace Tests\Feature\Settings;

use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Configuración: ya no está bloqueada y adentro tiene Usuarios y roles, Tratamientos y Mi perfil. */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuracion_abre_usuarios_y_roles(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)->get(route('settings.index'))->assertRedirect(route('users.index'));

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuarios y roles')
            ->assertSee(route('settings.treatments'))
            ->assertSee(route('password.change'))
            ->assertDontSee('Disponible próximamente" class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-body-md font-bold text-slate-600">', false);
    }

    public function test_catalogo_de_tratamientos(): void
    {
        $member = $this->member();
        Treatment::create(['organization_id' => $member->organization_id, 'name' => 'Limpieza dental', 'duration_minutes' => 45, 'price' => 800, 'sat_product_key' => '85121800']);
        Treatment::create(['organization_id' => $member->organization_id, 'name' => 'Implante dental', 'duration_minutes' => 90, 'price' => 18000]);
        $other = $this->member();
        Treatment::create(['organization_id' => $other->organization_id, 'name' => 'Servicio de otra clínica', 'duration_minutes' => 30, 'price' => 1]);

        $this->actingAsMember($member)->get(route('settings.treatments'))
            ->assertOk()
            ->assertSee('Catálogo de tratamientos')
            ->assertSee('Limpieza dental')
            ->assertSee('85121800')
            ->assertDontSee('Servicio de otra clínica');

        $this->get(route('settings.treatments', ['q' => 'implante']))
            ->assertSee('Implante dental')
            ->assertDontSee('Limpieza dental');
    }
}
