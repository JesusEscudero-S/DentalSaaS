<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Módulo 6. Expediente: control de aplicaciones de flúor (RF 6.6).
 * No existía en el esquema original; se agrega aquí sin tocar las migraciones del equipo.
 * El "año de control" no se guarda: se obtiene de applied_on (dato derivado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fluoride_applications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('organization_id');
            $table->uuid('patient_id');
            $table->date('applied_on');
            $table->uuid('applied_by_member_id');
            $table->string('notes', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('deleted_at')->nullable();

            $table->unique(['organization_id', 'id'], 'uq_fluoride_applications_org_id');
            $table->index(['organization_id', 'patient_id', 'applied_on'], 'idx_fluor_patient_date');
            $table->foreign(['organization_id', 'patient_id'], 'fk_fluor_patient')
                ->references(['organization_id', 'id'])->on('patients');
            $table->foreign(['organization_id', 'applied_by_member_id'], 'fk_fluor_member')
                ->references(['organization_id', 'id'])->on('organization_members');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fluoride_applications');
    }
};
