<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Models\PatientGuardian;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Contactos de emergencia (RF 3.5 – 3.7), guardados en patient_guardians.
 * La tabla no tiene status ni deleted_at, por lo que el borrado es físico
 * (ninguna otra tabla la referencia) y queda registrado en la bitácora.
 */
class PatientGuardianService
{
    private const FIELDS = ['full_name', 'relationship', 'phone', 'email'];

    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(Patient $patient, array $data): PatientGuardian
    {
        return DB::transaction(function () use ($patient, $data) {
            $guardian = $patient->guardians()->create([
                ...array_intersect_key($data, array_flip(self::FIELDS)),
                'organization_id' => $patient->organization_id,
                // El primer contacto registrado queda como principal.
                'is_primary' => ! $patient->guardians()->exists(),
            ]);

            $this->audit->log($guardian, AuditLogger::INSERT, ['patient_id' => $patient->id]);

            return $guardian;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(PatientGuardian $guardian, array $data): PatientGuardian
    {
        return DB::transaction(function () use ($guardian, $data) {
            $guardian->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $changed = array_keys($guardian->getDirty());
            $guardian->save();

            if ($changed) {
                $this->audit->log($guardian, AuditLogger::UPDATE, ['fields' => $changed]);
            }

            return $guardian;
        });
    }

    public function delete(PatientGuardian $guardian): void
    {
        DB::transaction(function () use ($guardian) {
            $wasPrimary = $guardian->is_primary;
            $patientId = $guardian->patient_id;

            $this->audit->log($guardian, AuditLogger::DELETE, ['patient_id' => $patientId]);
            $guardian->delete();

            if ($wasPrimary) {
                PatientGuardian::where('patient_id', $patientId)->orderBy('full_name')->first()?->update(['is_primary' => true]);
            }
        });
    }
}
