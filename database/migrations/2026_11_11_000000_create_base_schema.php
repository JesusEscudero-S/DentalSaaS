<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema base de Dental SaaS.
 *
 * La fuente de verdad es database/schema/dentalsaas.sql (copia de "Dental Saas.sql").
 * Si la BD ya fue importada a mano (tabla organizations existente), no hace nada;
 * así la misma migración sirve para la BD de desarrollo y para BDs nuevas (pruebas, otros equipos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) {
            return;
        }

        DB::unprepared(file_get_contents(database_path('schema/dentalsaas.sql')));
    }

    public function down(): void
    {
        // El esquema base no se revierte: se recrea la BD con migrate:fresh.
    }
};
