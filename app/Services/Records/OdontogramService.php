<?php

namespace App\Services\Records;

use App\Enums\Dentition;
use App\Enums\ToothCondition;
use App\Models\FluorideApplication;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\ToothFinding;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Odontograma (RF 6.3) y control de aplicaciones de flúor (RF 6.6).
 *
 * Un hallazgo nunca se sobrescribe: el vigente se cierra (superseded_at) y se crea uno nuevo.
 */
class OdontogramService
{
    public function __construct(private AuditLogger $audit) {}

    /** Estado actual de cada pieza: [tooth_fdi => ToothFinding]. Las piezas sin hallazgo se ven como "Sano". */
    public function current(Patient $patient): Collection
    {
        return ToothFinding::query()
            ->where('patient_id', $patient->id)
            ->current()
            ->get()
            ->keyBy('tooth_fdi');
    }

    /** Últimos cambios del odontograma (incluye los hallazgos reemplazados). */
    public function history(Patient $patient, int $limit = 15): Collection
    {
        return ToothFinding::query()
            ->where('patient_id', $patient->id)
            ->with('author.user')
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Registra el estado de una pieza. Las caras solo aplican a caries y restauración.
     *
     * @param  array<int, string>  $surfaces
     */
    public function record(Patient $patient, int $tooth, ToothCondition $condition, array $surfaces, OrganizationMember $author): ToothFinding
    {
        $dentition = Dentition::forTooth($tooth)
            ?? throw new DomainException("La pieza {$tooth} no es una pieza FDI válida.");

        $surfaces = $condition->usesSurfaces() ? array_values(array_unique($surfaces)) : [];
        sort($surfaces);

        return DB::transaction(function () use ($patient, $tooth, $dentition, $condition, $surfaces, $author) {
            // Bloquea el hallazgo vigente: dos personas editando el mismo diente no se pisan.
            $current = ToothFinding::query()
                ->where('patient_id', $patient->id)
                ->where('tooth_fdi', $tooth)
                ->current()
                ->lockForUpdate()
                ->first();

            if ($current && $current->condition === $condition && ($current->surfaces ?? []) === $surfaces) {
                throw new DomainException("La pieza {$tooth} ya tiene ese estado.");
            }

            $now = Carbon::now();

            // Primero se cierra el vigente: la BD solo permite uno vigente por pieza (uq_tooth_current).
            $current?->update(['superseded_at' => $now]);

            $finding = ToothFinding::create([
                'patient_id' => $patient->id,
                'tooth_fdi' => $tooth,
                'dentition' => $dentition,
                'condition' => $condition,
                'surfaces' => $surfaces ?: null,
                'author_member_id' => $author->id,
                'recorded_at' => $now,
            ]);

            $this->audit->log($finding, AuditLogger::INSERT, [
                'tooth_fdi' => $tooth,
                'condition' => [$current?->condition->value ?? 'sano', $condition->value],
            ]);

            return $finding;
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Flúor (RF 6.6)
    // ─────────────────────────────────────────────────────────────

    /**
     * Aplicaciones agrupadas por año de control, del más reciente al más antiguo:
     * [2026 => [aplicación, aplicación], 2025 => [...]]
     */
    public function fluorideByYear(Patient $patient): Collection
    {
        return FluorideApplication::query()
            ->where('patient_id', $patient->id)
            ->with('appliedBy.user')
            ->orderBy('applied_on')
            ->get()
            ->groupBy(fn (FluorideApplication $application) => $application->applied_on->year)
            ->sortKeysDesc();
    }

    /** @param  array{applied_on: string, notes?: string|null}  $data */
    public function addFluoride(Patient $patient, array $data, OrganizationMember $member): FluorideApplication
    {
        return DB::transaction(function () use ($patient, $data, $member) {
            $application = FluorideApplication::create([
                'patient_id' => $patient->id,
                'applied_on' => $data['applied_on'],
                'applied_by_member_id' => $member->id,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);

            $this->audit->log($application, AuditLogger::INSERT, ['applied_on' => $data['applied_on']]);

            return $application;
        });
    }

    /** Baja lógica (deleted_at): para corregir un registro equivocado. */
    public function removeFluoride(FluorideApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $application->delete();
            $this->audit->log($application, AuditLogger::DELETE);
        });
    }
}
