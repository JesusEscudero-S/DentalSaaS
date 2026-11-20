<?php

namespace App\Services\Records;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Expediente: antecedentes (RF 6.2) e historial de consultas.
 */
class MedicalRecordService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * RF 6.2: alergias y antecedentes/enfermedades crónicas. Ambos pueden quedar vacíos (NULLABLE).
     * La tabla no tiene updated_at: el cambio queda en audit_log (solo nombres de campos).
     *
     * @param  array{allergies?: string|null, medical_history?: string|null}  $data
     */
    public function updateHistory(MedicalRecord $record, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $record->fill([
                'allergies' => filled($data['allergies'] ?? null) ? trim($data['allergies']) : null,
                'medical_history' => filled($data['medical_history'] ?? null) ? trim($data['medical_history']) : null,
            ]);

            $changed = array_keys($record->getDirty());
            if ($changed) {
                $record->save();
                $this->audit->log($record, AuditLogger::UPDATE, ['fields' => $changed]);
            }

            return $record;
        });
    }

    /**
     * Pestaña Historial (Figma): consultas realizadas + citas próximas "(programada)".
     * Devuelve filas listas para la vista: date, doctor, text, scheduled.
     *
     * @return Collection<int, array{date: Carbon, doctor: string, text: string, scheduled: bool, note: ?ClinicalNote}>
     */
    public function timeline(Patient $patient, MedicalRecord $record): Collection
    {
        $encounters = ClinicalEncounter::query()
            ->where('medical_record_id', $record->id)
            ->with(['doctor.user', 'appointment', 'notes' => fn ($q) => $q->orderBy('created_at')])
            ->get()
            ->map(function (ClinicalEncounter $encounter) {
                $note = $encounter->notes->first();

                return [
                    'date' => $encounter->occurred_at,
                    'doctor' => $encounter->doctor->displayName(),
                    'text' => $note?->summary() ?: ($encounter->appointment?->notes ?? 'Consulta sin nota'),
                    'scheduled' => false,
                    'note' => $note,
                ];
            });

        $upcoming = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [AppointmentStatus::Pendiente->value, AppointmentStatus::Confirmada->value])
            ->where('starts_at', '>=', now())
            ->with('doctor.user')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'date' => $appointment->starts_at,
                'doctor' => $appointment->doctor->displayName(),
                'text' => $appointment->notes,
                'scheduled' => true,
                'note' => null,
            ]);

        // Como en Figma: lo realizado (lo más reciente arriba) y al final lo programado.
        return $encounters->sortByDesc(fn ($row) => $row['date'])->values()->concat($upcoming);
    }
}
