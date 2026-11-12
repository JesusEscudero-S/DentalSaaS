<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/** Catálogo global de permisos. Cada módulo agrega aquí los suyos. */
class PermissionSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    public const PERMISSIONS = [
        'pacientes' => [
            'ver' => 'Consultar pacientes y sus contactos de emergencia',
            'crear' => 'Registrar nuevos pacientes',
            'editar' => 'Actualizar perfil y contactos de emergencia',
            'desactivar' => 'Desactivar y reactivar pacientes',
            'exportar' => 'Exportar el listado de pacientes',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(['module' => $module, 'action' => $action], ['description' => $description]);
            }
        }
    }
}
