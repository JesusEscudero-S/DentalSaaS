<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Módulo 1: cambiar la propia contraseña (P7), cierre de sesión al restablecerla y último acceso. */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambia_su_contrasena_con_la_politica(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)->get(route('password.change'))->assertOk();

        $this->put(route('password.change.update'), [
            'current_password' => 'password', 'password' => 'debil', 'password_confirmation' => 'debil',
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => 'password', 'password' => 'Nueva2026', 'password_confirmation' => 'Nueva2026',
        ])->assertRedirect(route('home'));

        $this->assertTrue(Hash::check('Nueva2026', $member->user->fresh()->password));
        $this->get(route('patients.index'))->assertOk(); // su propia sesión sigue abierta
    }

    public function test_si_le_restablecen_la_contrasena_se_cierra_su_sesion(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)->get(route('patients.index'))->assertOk();

        $member->user->update(['password' => 'OtraTemporal9']); // lo que hace "Restablecer contraseña"

        $this->get(route('patients.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inicio_de_sesion_registra_ultimo_acceso_y_bitacora(): void
    {
        $user = $this->member()->user;

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_log', ['actor_user_id' => $user->id, 'action' => 'login']);
    }
}
