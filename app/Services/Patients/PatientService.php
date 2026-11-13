<?php

namespace App\Services\Patients;

use App\Enums\PatientStatus;
use App\Models\ConsentAcceptance;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientFiscalProfile;
use App\Services\AuditLogger;
use App\Services\FolioService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PatientService
{
    private const PATIENT_FIELDS = ['full_name', 'birth_date', 'sex', 'phone', 'email', 'assigned_doctor_member_id'];

    private const FISCAL_FIELDS = ['rfc', 'legal_name', 'tax_regime_code', 'fiscal_postal_code'];

    public function __construct(
        private FolioService $folios,
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * RF 3.1: registra al paciente con folio, su expediente en blanco (1 a 1),
     * la aceptación de la responsiva (RN-P4) y, opcionalmente, sus datos fiscales.
     *
     * @param  array<string, mixed>  $data  datos validados por StorePatientRequest
     */
    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $patient = Patient::create([
                ...Arr::only($data, self::PATIENT_FIELDS),
                'folio' => $this->folios->next('patient', 'PAC-'),
                'status' => PatientStatus::Active,
            ]);

            MedicalRecord::create(['patient_id' => $patient->id]);

            ConsentAcceptance::create([
                'patient_id' => $patient->id,
                'accepted_at' => now(),
                'accepted_by_name' => $data['consent_signed_by'],
                'method' => $data['consent_method'],
                'witness_member_id' => $this->current->member()?->id,
            ]);

            $this->syncFiscalProfile($patient, $data);

            $this->audit->log($patient, AuditLogger::INSERT, ['folio' => $patient->folio]);

            return $patient;
        });
    }

    /**
     * RF 3.3: actualiza el perfil (updated_at se actualiza automáticamente).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Patient $patient, array $data): Patient
    {
        return DB::transaction(function () use ($patient, $data) {
            $patient->fill(Arr::only($data, self::PATIENT_FIELDS));
            $changed = array_keys($patient->getDirty());
            $patient->save();

            if ($this->syncFiscalProfile($patient, $data)) {
                $changed[] = 'fiscal_profile';
            }

            if ($changed) {
                $this->audit->log($patient, AuditLogger::UPDATE, ['fields' => $changed]);
            }

            return $patient;
        });
    }

    /** RF 3.4 / RN-P3: baja lógica. */
    public function deactivate(Patient $patient): void
    {
        $this->changeStatus($patient, PatientStatus::Inactive);
    }

    public function reactivate(Patient $patient): void
    {
        $this->changeStatus($patient, PatientStatus::Active);
    }

    private function changeStatus(Patient $patient, PatientStatus $status): void
    {
        if ($patient->status === $status) {
            return;
        }

        DB::transaction(function () use ($patient, $status) {
            $from = $patient->status->value;
            $patient->update(['status' => $status]);
            $this->audit->log($patient, AuditLogger::UPDATE, ['status' => [$from, $status->value]]);
        });
    }

    /**
     * Crea, actualiza o elimina el perfil fiscal predeterminado.
     * Los cuatro campos son obligatorios en la BD: si vienen vacíos, el perfil se quita.
     *
     * @param  array<string, mixed>  $data
     * @return bool si hubo cambios
     */
    private function syncFiscalProfile(Patient $patient, array $data): bool
    {
        $fiscal = array_filter(Arr::only($data, self::FISCAL_FIELDS), fn ($v) => filled($v));
        $profile = $patient->fiscalProfile()->first();

        if (count($fiscal) !== count(self::FISCAL_FIELDS)) {
            if ($profile) {
                $profile->delete();

                return true;
            }

            return false;
        }

        if (! $profile) {
            PatientFiscalProfile::create([...$fiscal, 'patient_id' => $patient->id, 'is_default' => true]);

            return true;
        }

        $profile->fill($fiscal);
        $dirty = $profile->isDirty();
        $profile->save();

        return $dirty;
    }
}
