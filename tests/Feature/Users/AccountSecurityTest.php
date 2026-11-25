<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Módulo 1: contraseña temporal (RN-U6), bloqueo por intentos (P1) y último acceso. */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_contrasena_temporal_solo_puede_cambiarla(): void
    {
        $member = $this->member();
        $member->user->update(['must_change_password' => true]);

        $this->actingAsMember($member)->get(route('patients.index'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('contraseña temporal');
    }

    public function test_cambia_su_contrasena_con_la_politica(): void
    {
        $member = $this->member();
        $member->user->update(['must_change_password' => true]);

        $this->actingAsMember($member)->put(route('password.change.update'), [
            'current_password' => 'password', 'password' => 'debil', 'password_confirmation' => 'debil',
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => 'password', 'password' => 'Nueva2026', 'password_confirmation' => 'Nueva2026',
        ])->assertRedirect(route('home'));

        $user = $member->user->fresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Nueva2026', $user->password));
        $this->get(route('patients.index'))->assertOk();
    }

    public function test_si_le_restablecen_la_contrasena_se_cierra_su_sesion(): void
    {
        $member = $this->member();

        $this->actingAsMember($member)->get(route('patients.index'))->assertOk();

        $member->user->update(['password' => 'OtraTemporal9']); // lo que hace "Restablecer contraseña"

        $this->get(route('patients.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_bloquea_la_cuenta_tras_cinco_intentos(): void
    {
        $user = $this->member()->user;

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertDatabaseHas('audit_log', ['actor_user_id' => $user->id, 'action' => 'lock']);

        // Aunque ahora la contraseña sea la correcta, sigue bloqueada.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inicio_de_sesion_registra_ultimo_acceso(): void
    {
        $user = $this->member()->user;
        $user->update(['failed_login_attempts' => 2]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertDatabaseHas('audit_log', ['actor_user_id' => $user->id, 'action' => 'login']);
    }
}
