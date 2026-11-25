<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoOrganizationSeeder::class,
            TreatmentCatalogSeeder::class,   // catálogo de demo hasta que exista el módulo 5
            FinanceSeeder::class,
            PatientSeeder::class,
            AppointmentSeeder::class,
            RecordSeeder::class,
        ]);
    }
}
