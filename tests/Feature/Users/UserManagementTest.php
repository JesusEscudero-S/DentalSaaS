<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\DoctorProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Módulo 1. Gestión de usuarios. */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMember $admin;

    private Role $adminRole;

    private Role $doctorRole;

    private Role $secretaryRole;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::create(['commercial_name' => 'Clínica Prueba']);
        $this->adminRole = $this->role($organization, 'admin', 'Administrador', true);
        $this->doctorRole = $this->role($organization, 'odontologo', 'Odontólogo');
        $this->secretaryRole = $this->role($organization, 'secretaria', 'Secretaria/Asistente');

        $this->admin = $this->member($organization);
        $this->admin->update(['role_id' => $this->adminRole->id]);
    }

    private function role(Organization $organization, string $code, string $name, bool $admin = false): Role
    {
        return Role::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $name, 'is_system_admin' => $admin]);
    }

    private function teamMember(?Role $role = null, array $user = []): OrganizationMember
    {
        $member = $this->member($this->admin->organization);
        $member->update(['role_id' => ($role ?? $this->secretaryRole)->id]);
        if ($user) {
            $member->user->update($user);
        }

        return $member->fresh(['user', 'role']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'full_name' => 'laura gómez',
            'email' => 'Laura@Demo.test',
            'phone' => '55 1234-5678',
            'role_id' => $this->secretaryRole->id,
            ...$overrides,
        ];
    }

    public function test_lista_usuarios_de_la_clinica_con_filtros(): void
    {
        $doctor = $this->teamMember($this->doctorRole, ['full_name' => 'DR. JUAN PÉREZ']);
        $other = $this->member(); // otra clínica

        $this->actingAsMember($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('DR. JUAN PÉREZ')
            ->assertDontSee($other->user->full_name);

        $this->get(route('users.index', ['rol' => $this->secretaryRole->id]))
            ->assertOk()
            ->assertDontSee('DR. JUAN PÉREZ');

        $this->get(route('users.index', ['q' => 'juan']))->assertSee($doctor->user->email);
    }

    public function test_alta_de_usuario_con_contrasena_temporal(): void
    {
        $response = $this->actingAsMember($this->admin)->post(route('users.store'), $this->payload());

        $response->assertRedirect()->assertSessionHas('temp_password');
        $temp = session('temp_password')['password'];

        $user = User::where('email', 'laura@demo.test')->firstOrFail();
        $this->assertSame('LAURA GÓMEZ', $user->full_name);
        $this->assertSame('5512345678', $user->phone);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temp, $user->password));
        $this->assertMatchesRegularExpression('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $temp);

        $member = OrganizationMember::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($this->secretaryRole->id, $member->role_id);
        $this->assertTrue($member->is_active);
        $this->assertDatabaseHas('audit_log', ['entity_id' => $member->id, 'action' => 'insert']);
        $this->assertStringNotContainsString($temp, (string) AuditLog::latest('id')->first()->getRawOriginal('changed_fields'));
    }

    public function test_odontologo_nuevo_queda_con_perfil_de_doctor(): void
    {
        $this->actingAsMember($this->admin)->post(route('users.store'), $this->payload([
            'role_id' => $this->doctorRole->id, 'professional_license' => '7654321', 'specialty' => 'Endodoncia',
        ]))->assertSessionHasNoErrors();

        $member = OrganizationMember::whereHas('user', fn ($q) => $q->where('email', 'laura@demo.test'))->firstOrFail();
        $this->assertDatabaseHas('doctor_profiles', ['member_id' => $member->id, 'professional_license' => '7654321']);
    }

    public function test_correo_repetido_y_validaciones(): void
    {
        $this->teamMember(null, ['email' => 'laura@demo.test']);

        $this->actingAsMember($this->admin)
            ->post(route('users.store'), $this->payload(['phone' => '123', 'role_id' => 'no-existe']))
            ->assertSessionHasErrorsIn('createUser', ['email', 'phone', 'role_id']);
    }

    public function test_no_acepta_rol_de_otra_clinica(): void
    {
        $foreign = $this->role(Organization::create(['commercial_name' => 'Otra']), 'admin', 'Administrador', true);

        $this->actingAsMember($this->admin)
            ->post(route('users.store'), $this->payload(['role_id' => $foreign->id]))
            ->assertSessionHasErrorsIn('createUser', ['role_id']);
    }

    public function test_modificar_datos_y_rol(): void
    {
        $member = $this->teamMember();

        $this->actingAsMember($this->admin)->put(route('users.update', $member), $this->payload([
            'email' => 'nuevo@demo.test', 'role_id' => $this->doctorRole->id,
        ]))->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('nuevo@demo.test', $member->user->email);
        $this->assertSame($this->doctorRole->id, $member->role_id);
        $this->assertTrue(DoctorProfile::where('member_id', $member->id)->exists());
        $this->assertDatabaseHas('audit_log', ['entity_id' => $member->id, 'action' => 'update']);
    }

    public function test_desactivar_y_reactivar_conserva_el_registro(): void
    {
        $member = $this->teamMember();

        $this->actingAsMember($this->admin)->patch(route('users.deactivate', $member))->assertSessionHas('toast.type', 'success');
        $this->assertFalse($member->fresh()->is_active);

        $this->patch(route('users.reactivate', $member));
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_usuario_desactivado_pierde_el_acceso(): void
    {
        $member = $this->teamMember();
        $member->update(['is_active' => false]);

        $this->actingAsMember($member)->get(route('patients.index'))->assertRedirect(route('login'));
    }

    public function test_no_puede_desactivar_su_propia_cuenta(): void
    {
        $this->actingAsMember($this->admin)->patch(route('users.deactivate', $this->admin))
            ->assertSessionHas('toast.type', 'error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_no_puede_quedarse_sin_administrador(): void
    {
        // Otro admin desactiva al primero: queda uno solo, que ya no se puede desactivar ni degradar.
        $second = $this->teamMember($this->adminRole);
        $this->actingAsMember($second)->patch(route('users.deactivate', $this->admin))->assertSessionHas('toast.type', 'success');

        $this->actingAsMember($second)->put(route('users.update', $second), $this->payload([
            'email' => $second->user->email, 'role_id' => $this->secretaryRole->id,
        ]))->assertSessionHas('toast.type', 'error');

        $this->assertSame($this->adminRole->id, $second->fresh()->role_id);
    }

    public function test_restablecer_contrasena(): void
    {
        $member = $this->teamMember(null, ['locked_until' => now()->addMinutes(10), 'failed_login_attempts' => 3]);

        $this->actingAsMember($this->admin)->patch(route('users.reset-password', $member))->assertSessionHas('temp_password');

        $user = $member->user->fresh();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->locked_until);
        $this->assertTrue(Hash::check(session('temp_password')['password'], $user->password));
    }

    public function test_usuarios_de_otra_clinica_dan_404(): void
    {
        $other = $this->member();

        $this->actingAsMember($this->admin)->patch(route('users.deactivate', $other))->assertNotFound();
    }

    public function test_descarga_pdf(): void
    {
        $this->actingAsMember($this->admin)->get(route('users.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
