<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la línea 919 del SQL original: la FK estaba invertida
 * (organization_members -> doctor_profiles), lo que impedía registrar cualquier
 * miembro sin un perfil de doctor previo.
 *
 * Dirección correcta: doctor_profiles (organization_id, member_id)
 *                     -> organization_members (organization_id, id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $inverted = $this->foreignKeyName('organization_members', 'doctor_profiles');

        if ($inverted) {
            DB::statement("ALTER TABLE `organization_members` DROP FOREIGN KEY `{$inverted}`");
        }

        if (! $this->foreignKeyName('doctor_profiles', 'organization_members')) {
            Schema::table('doctor_profiles', function ($table) {
                $table->foreign(['organization_id', 'member_id'], 'doctor_profiles_member_fk')
                    ->references(['organization_id', 'id'])
                    ->on('organization_members');
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyName('doctor_profiles', 'organization_members')) {
            Schema::table('doctor_profiles', fn ($table) => $table->dropForeign('doctor_profiles_member_fk'));
        }
    }

    private function foreignKeyName(string $table, string $referencedTable): ?string
    {
        return DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('referenced_table_name', $referencedTable)
            ->value('constraint_name');
    }
};
