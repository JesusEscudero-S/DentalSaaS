<?php

namespace Database\Seeders;

use App\Models\DoctorProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Organización de demostración con los cuatro roles del negocio y su equipo.
 * Los nombres siguen los usados en Figma.
 */
class DemoOrganizationSeeder extends Seeder
{
    public const ORGANIZATION = 'Clínica Dental Sonrisas';

    /** code => [nombre, es admin, permisos de pacientes] */
    private const ROLES = [
        'admin' => ['Administrador', true, []],
        'jefe_odontologia' => ['Jefe de odontología', false, ['ver', 'crear', 'editar', 'desactivar', 'exportar']],
        'secretaria' => ['Secretaria/Asistente', false, ['ver', 'crear', 'editar', 'desactivar', 'exportar']],
        'odontologo' => ['Odontólogo', false, ['ver']],
    ];

    /** [nombre, email, rol, cédula, especialidad] */
    private const TEAM = [
        ['Administrador Demo', 'admin@demo.test', 'admin', null, null],
        ['Dra. Mariana Reyes', 'mariana.reyes@demo.test', 'jefe_odontologia', '10293847', 'Odontología general'],
        ['Karla Méndez', 'karla.mendez@demo.test', 'secretaria', null, null],
        ['Dr. Andrés Salazar', 'andres.salazar@demo.test', 'odontologo', '11223344', 'Ortodoncia'],
        ['Dra. Lucía Fonseca', 'lucia.fonseca@demo.test', 'odontologo', '12345678', 'Endodoncia'],
        ['Dr. Hugo Beltrán', 'hugo.beltran@demo.test', 'odontologo', '13572468', 'Periodoncia'],
        ['Dra. Paola Ríos', 'paola.rios@demo.test', 'odontologo', '14725836', 'Odontopediatría'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $organization = Organization::firstOrCreate(
                ['commercial_name' => self::ORGANIZATION],
                ['legal_name' => 'CLINICA DENTAL SONRISAS SA DE CV', 'phone' => '5555555555', 'email' => 'contacto@demo.test'],
            );

            $roles = [];
            foreach (self::ROLES as $code => [$name, $isAdmin, $actions]) {
                $role = Role::firstOrCreate(
                    ['organization_id' => $organization->id, 'code' => $code],
                    ['name' => $name, 'is_system_admin' => $isAdmin],
                );

                foreach (Permission::where('module', 'pacientes')->whereIn('action', $actions)->get() as $permission) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'organization_id' => $organization->id,
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ]);
                }

                $roles[$code] = $role;
            }

            foreach (self::TEAM as [$name, $email, $roleCode, $license, $specialty]) {
                $user = User::firstOrCreate(['email' => $email], ['full_name' => $name]);

                $member = OrganizationMember::firstOrCreate(
                    ['organization_id' => $organization->id, 'user_id' => $user->id],
                    ['role_id' => $roles[$roleCode]->id],
                );

                if ($license) {
                    DoctorProfile::firstOrCreate(
                        ['member_id' => $member->id],
                        ['organization_id' => $organization->id, 'professional_license' => $license, 'specialty' => $specialty],
                    );
                }
            }
        });
    }
}
