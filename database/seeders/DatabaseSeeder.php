<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoOrganizationSeeder::class,
            FinanceSeeder::class,
            PatientSeeder::class,
            AppointmentSeeder::class,
            RecordSeeder::class,
        ]);
    }
}
