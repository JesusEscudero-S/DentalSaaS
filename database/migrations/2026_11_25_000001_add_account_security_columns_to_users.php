<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 1. Gestión de usuarios: columnas que el documento de requerimientos pide
 * y el esquema original no tiene.
 *  - phone: teléfono de contacto (10 dígitos).
 *  - must_change_password: RN-U6, la contraseña temporal se cambia en el primer acceso.
 *  - failed_login_attempts / locked_until: P1, bloqueo de 15 min tras 5 intentos fallidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 10)->nullable()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('must_change_password');
            $table->dateTime('locked_until')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'must_change_password', 'failed_login_attempts', 'locked_until']);
        });
    }
};
