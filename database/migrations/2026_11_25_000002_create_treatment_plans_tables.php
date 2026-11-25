<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 7. Gestión de tratamientos (pendiente de acordar con el equipo).
 *
 * El esquema original guarda los procedimientos en quotes / quote_items / patient_treatments,
 * pero no tiene dónde poner el diagnóstico, el estatus del plan (Propuesto → Autorizado →
 * En proceso → Concluido / Cancelado), el motivo de cancelación, las versiones (RN-T5, P8)
 * ni las sesiones ("Desarrollo de tratamiento"). Estas tablas lo agregan sin tocar las existentes:
 *  - treatment_plans: el plan; su presupuesto vigente es quote_id.
 *  - treatment_plan_versions: cada cambio de procedimientos o costos crea un presupuesto nuevo.
 *  - treatment_sessions (+ items): sesiones; no se borran (RN-T4).
 *  - treatment_session_notes: correcciones de una sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('organization_id');
            $table->uuid('patient_id');
            $table->uuid('doctor_member_id');
            $table->uuid('quote_id');
            $table->text('diagnosis');
            $table->date('diagnosed_on');
            $table->enum('status', ['propuesto', 'autorizado', 'en_proceso', 'concluido', 'cancelado'])->default('propuesto');
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('requires_reauthorization')->default(false);
            $table->uuid('consent_acceptance_id')->nullable();
            $table->dateTime('authorized_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['organization_id', 'id'], 'uq_treatment_plans_org_id');
            $table->index(['organization_id', 'patient_id'], 'idx_treatment_plans_patient');
            $table->foreign(['organization_id', 'patient_id'], 'fk_tplan_patient')->references(['organization_id', 'id'])->on('patients');
            $table->foreign(['organization_id', 'doctor_member_id'], 'fk_tplan_doctor')->references(['organization_id', 'id'])->on('organization_members');
            $table->foreign(['organization_id', 'quote_id'], 'fk_tplan_quote')->references(['organization_id', 'id'])->on('quotes');
            $table->foreign('consent_acceptance_id', 'fk_tplan_consent')->references('id')->on('consent_acceptances');
            $table->foreign('created_by_user_id', 'fk_tplan_creator')->references('id')->on('users');
        });

        Schema::create('treatment_plan_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('organization_id');
            $table->uuid('plan_id');
            $table->unsignedSmallInteger('version');
            $table->uuid('quote_id');
            $table->text('change_reason')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['plan_id', 'version'], 'uq_tplan_versions');
            $table->foreign(['organization_id', 'plan_id'], 'fk_tpv_plan')->references(['organization_id', 'id'])->on('treatment_plans');
            $table->foreign(['organization_id', 'quote_id'], 'fk_tpv_quote')->references(['organization_id', 'id'])->on('quotes');
            $table->foreign('created_by_user_id', 'fk_tpv_creator')->references('id')->on('users');
        });

        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('organization_id');
            $table->uuid('plan_id');
            $table->uuid('encounter_id')->nullable();
            $table->uuid('doctor_member_id');
            $table->date('performed_on');
            $table->text('activities');
            $table->uuid('created_by_user_id')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['organization_id', 'id'], 'uq_treatment_sessions_org_id');
            $table->foreign(['organization_id', 'plan_id'], 'fk_tses_plan')->references(['organization_id', 'id'])->on('treatment_plans');
            $table->foreign('encounter_id', 'fk_tses_encounter')->references('id')->on('clinical_encounters');
            $table->foreign(['organization_id', 'doctor_member_id'], 'fk_tses_doctor')->references(['organization_id', 'id'])->on('organization_members');
            $table->foreign('created_by_user_id', 'fk_tses_creator')->references('id')->on('users');
        });

        Schema::create('treatment_session_items', function (Blueprint $table) {
            $table->uuid('session_id');
            $table->uuid('patient_treatment_id');

            $table->primary(['session_id', 'patient_treatment_id']);
            $table->foreign('session_id', 'fk_tsi_session')->references('id')->on('treatment_sessions');
            $table->foreign('patient_treatment_id', 'fk_tsi_pt')->references('id')->on('patient_treatments');
        });

        Schema::create('treatment_session_notes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('organization_id');
            $table->uuid('session_id');
            $table->text('note');
            $table->uuid('author_user_id')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign(['organization_id', 'session_id'], 'fk_tsn_session')->references(['organization_id', 'id'])->on('treatment_sessions');
            $table->foreign('author_user_id', 'fk_tsn_author')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_session_notes');
        Schema::dropIfExists('treatment_session_items');
        Schema::dropIfExists('treatment_sessions');
        Schema::dropIfExists('treatment_plan_versions');
        Schema::dropIfExists('treatment_plans');
    }
};
