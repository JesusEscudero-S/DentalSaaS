<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Treatment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogo de servicios de demostración (los del diseño "Catálogo de tratamientos").
 * El catálogo real lo administra el módulo 5 (Servicios odontológicos); este seeder solo existe para
 * poder probar Tratamientos (módulo 7) mientras ese módulo no esté listo.
 */
class TreatmentCatalogSeeder extends Seeder
{
    /** [servicio, categoría, minutos, precio] */
    private const CATALOG = [
        ['Consulta de valoración', 'Diagnóstico', 30, 450],
        ['Limpieza dental', 'Preventiva', 45, 800],
        ['Resina (1 superficie)', 'Restaurativa', 45, 950],
        ['Endodoncia unirradicular', 'Endodoncia', 90, 3200],
        ['Extracción simple', 'Cirugía', 40, 1200],
        ['Corona de porcelana', 'Prótesis', 60, 6500],
        ['Implante dental', 'Implantología', 90, 18000],
        ['Blanqueamiento', 'Estética', 60, 2800],
        ['Brackets metálicos', 'Ortodoncia', 60, 12000],
        ['Ajuste de ortodoncia', 'Ortodoncia', 30, 600],
    ];

    public function run(): void
    {
        $organization = Organization::where('commercial_name', DemoOrganizationSeeder::ORGANIZATION)->firstOrFail();

        DB::transaction(function () use ($organization) {
            $categories = [];

            foreach (self::CATALOG as [$name, $category, $minutes, $price]) {
                $categories[$category] ??= DB::table('treatment_categories')
                    ->where('organization_id', $organization->id)->where('name', $category)->value('id')
                    ?? tap((string) Str::uuid(), fn ($id) => DB::table('treatment_categories')->insert([
                        'id' => $id, 'organization_id' => $organization->id, 'name' => $category,
                    ]));

                Treatment::firstOrCreate(
                    ['organization_id' => $organization->id, 'name' => $name],
                    ['category_id' => $categories[$category], 'duration_minutes' => $minutes, 'price' => $price, 'sat_product_key' => '85121800'],
                );
            }
        });
    }
}
