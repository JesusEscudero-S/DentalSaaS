<?php

namespace App\Services\Users;

use App\Models\DoctorProfile;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Módulo 1. Gestión de usuarios.
 *
 * Un "usuario" de la clínica es su membresía (organization_members): ahí viven el rol y el
 * estatus. Los datos personales y la contraseña están en users.
 */
class UserService
{
    /** Roles que atienden pacientes: necesitan perfil de doctor para aparecer en Agenda y Expediente. */
    public const DOCTOR_ROLES = ['odontologo', 'jefe_odontologia'];

    private const USER_FIELDS = ['full_name', 'email', 'phone'];

    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * Alta de usuario (diagrama 1): cuenta activa con contraseña temporal que debe cambiar (RN-U6).
     *
     * @param  array<string, mixed>  $data  validados por StoreUserRequest
     * @return array{0: OrganizationMember, 1: string} la membresía y la contraseña temporal en claro
     */
    public function create(array $data): array
    {
        $password = TemporaryPassword::generate();

        $member = DB::transaction(function () use ($data, $password) {
            $user = User::create([
                ...Arr::only($data, self::USER_FIELDS),
                'password' => $password,
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $member = OrganizationMember::create([
                'user_id' => $user->id,
                'role_id' => $data['role_id'],
                'is_active' => true,
            ]);

            $this->syncDoctorProfile($member, $data);

            $this->audit->log($member, AuditLogger::INSERT, [
                'email' => $user->email,
                'role' => $member->role->code,
            ]);

            return $member;
        });

        return [$member, $password];
    }

    /**
     * Modificar usuario (diagrama 2). El correo es el identificador de acceso y sí puede cambiar;
     * el resto de la cuenta (contraseña, estatus) se maneja con sus propias acciones.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(OrganizationMember $member, array $data): void
    {
        $newRole = Role::findOrFail($data['role_id']);

        if ($member->role_id !== $newRole->id && $member->isLastActiveAdmin() && ! $newRole->is_system_admin) {
            throw new DomainException('No puedes quitarle el rol de administrador al último administrador activo.');
        }

        $this->guardTrigger(function () use ($member, $data, $newRole) {
            DB::transaction(function () use ($member, $data, $newRole) {
                $user = $member->user;
                $user->fill(Arr::only($data, self::USER_FIELDS));
                $changed = array_keys($user->getDirty());
                $user->save();

                $oldRole = $member->role?->code;
                if ($member->role_id !== $newRole->id) {
                    $member->update(['role_id' => $newRole->id]);
                    $member->setRelation('role', $newRole);
                    $changed['role'] = [$oldRole, $newRole->code];
                }

                if ($this->syncDoctorProfile($member, $data)) {
                    $changed[] = 'doctor_profile';
                }

                if ($changed) {
                    $this->audit->log($member, AuditLogger::UPDATE, ['fields' => $changed]);
                }
            });
        });
    }

    /** Diagrama 3 / RN-U4 y RN-U5: baja lógica, sin tocar el historial. */
    public function deactivate(OrganizationMember $member): void
    {
        if ($member->id === $this->current->member()?->id) {
            throw new DomainException('No puedes desactivar tu propia cuenta.');
        }

        if ($member->isLastActiveAdmin()) {
            throw new DomainException('No puedes desactivar al último administrador activo de la clínica.');
        }

        $this->changeStatus($member, false);
    }

    public function reactivate(OrganizationMember $member): void
    {
        $this->changeStatus($member, true);
    }

    /**
     * Diagrama 3, rama "Restablecer contraseña": genera una temporal y marca que debe cambiarla.
     * Al cambiar el hash, el middleware session.current cierra las sesiones abiertas del usuario.
     */
    public function resetPassword(OrganizationMember $member): string
    {
        if ($member->id === $this->current->member()?->id) {
            throw new DomainException('Para cambiar tu propia contraseña usa la opción Cambiar contraseña.');
        }

        $password = TemporaryPassword::generate();

        DB::transaction(function () use ($member, $password) {
            $member->user->update([
                'password' => $password,
                'must_change_password' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            $this->audit->log($member, AuditLogger::UPDATE, ['password' => 'reset']);
        });

        return $password;
    }

    private function changeStatus(OrganizationMember $member, bool $active): void
    {
        if ($member->is_active === $active) {
            return;
        }

        $this->guardTrigger(function () use ($member, $active) {
            DB::transaction(function () use ($member, $active) {
                $member = OrganizationMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
                $member->update(['is_active' => $active]);
                $this->audit->log($member, AuditLogger::UPDATE, ['is_active' => [! $active, $active]]);
            });
        });
    }

    /**
     * Crea o actualiza el perfil de doctor para los roles clínicos.
     * Si el usuario deja de ser doctor el perfil se conserva: sus citas y notas siguen ligadas a él.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDoctorProfile(OrganizationMember $member, array $data): bool
    {
        if (! in_array($member->role?->code, self::DOCTOR_ROLES, true)) {
            return false;
        }

        $profile = DoctorProfile::firstOrNew(['member_id' => $member->id], ['organization_id' => $member->organization_id]);
        $profile->fill([
            'professional_license' => $data['professional_license'] ?? null,
            'specialty' => $data['specialty'] ?? null,
        ]);
        $dirty = ! $profile->exists || $profile->isDirty();
        $profile->save();

        return $dirty;
    }

    /** El trigger del último administrador (SQLSTATE 45000) es la última defensa: se muestra como toast. */
    private function guardTrigger(callable $callback): void
    {
        try {
            $callback();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                throw new DomainException('La clínica debe conservar al menos un administrador activo.');
            }

            throw $e;
        }
    }
}
