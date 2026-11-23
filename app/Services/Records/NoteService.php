<?php

namespace App\Services\Records;

use App\Enums\AppointmentStatus;
use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalNoteAddendum;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notas clínicas (RF 6.4 Registrar diagnóstico / 6.5 Modificar diagnóstico).
 *
 * Cada nota pertenece a una consulta (clinical_encounters) del expediente del paciente.
 * Flujo: borrador (editable) → firmada (inmutable). Firmar guarda un hash SHA-256 del contenido.
 * Una nota firmada solo admite adendas (versión + motivo), nunca cambios.
 */
class NoteService
{
    public const SIGNATURE_MECHANISM = 'sha256-v1';

    public function __construct(private AuditLogger $audit) {}

    /** RN-E1: el expediente 1:1. Los pacientes nuevos ya lo traen; si faltara, se crea. */
    public function recordFor(Patient $patient): MedicalRecord
    {
        return MedicalRecord::firstOrCreate(['patient_id' => $patient->id]);
    }

    /** Notas del paciente, de la más reciente a la más antigua. */
    public function notesFor(Patient $patient): Collection
    {
        // JOIN para poder ordenar por la fecha de la consulta (está en otra tabla).
        return ClinicalNote::query()
            ->select('clinical_notes.*')
            ->join('clinical_encounters', 'clinical_encounters.id', '=', 'clinical_notes.encounter_id')
            ->join('medical_records', 'medical_records.id', '=', 'clinical_encounters.medical_record_id')
            ->where('medical_records.patient_id', $patient->id)
            ->with(['encounter', 'responsibleDoctor.user', 'treatment', 'addenda'])
            ->orderByDesc('clinical_encounters.occurred_at')
            ->orderByDesc('clinical_notes.created_at')
            ->get();
    }

    /**
     * Citas del paciente que pueden ligarse a una nota: las que ya empezaron o terminaron
     * (en curso o completadas), de las más recientes a las más antiguas.
     */
    public function linkableAppointments(Patient $patient): Collection
    {
        return Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [AppointmentStatus::EnCurso->value, AppointmentStatus::Completada->value])
            ->with('doctor.user')
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();
    }

    /**
     * RF 6.4: registra una nota. Si $sign es true, la firma en el mismo paso (botón "Firmar nota").
     *
     * @param  array<string, mixed>  $data  validado por StoreClinicalNoteRequest
     */
    public function create(Patient $patient, array $data, OrganizationMember $author, bool $sign, string $errorBag): ClinicalNote
    {
        // Validar antes de escribir nada: si no puede firmar, que no quede un borrador a medias.
        if ($sign) {
            $this->assertCanSign($data['doctor_member_id'], $author, $errorBag);
        }

        return DB::transaction(function () use ($patient, $data, $author, $sign) {
            $encounter = $this->encounterFor($patient, $data);

            $note = ClinicalNote::create([
                'encounter_id' => $encounter->id,
                'note_type' => $data['note_type'],
                'author_member_id' => $author->id,
                'responsible_doctor_member_id' => $data['doctor_member_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                ...$this->content($data),
                'status' => NoteStatus::Borrador,
            ]);

            $this->audit->log($note, AuditLogger::INSERT, ['note_type' => $note->note_type->value, 'encounter_id' => $encounter->id]);

            return $sign ? $this->sign($note, $author) : $note;
        });
    }

    /**
     * RF 6.5: modifica una nota en borrador. Una firmada no se toca (y el trigger lo impediría).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ClinicalNote $note, array $data, OrganizationMember $actor, bool $sign, string $errorBag): ClinicalNote
    {
        if ($sign) {
            $this->assertCanSign($data['doctor_member_id'], $actor, $errorBag);
        }

        return DB::transaction(function () use ($note, $data, $actor, $sign) {
            $note = $this->lock($note);

            if ($note->isSigned()) {
                throw new DomainException('La nota ya está firmada: para corregirla agrega una adenda.');
            }

            $note->fill([
                'responsible_doctor_member_id' => $data['doctor_member_id'],
                ...$this->content($data),
            ]);
            $changed = array_keys($note->getDirty());
            $note->save();

            // Solo nombres de campos: nunca texto clínico en la bitácora.
            $this->audit->log($note, AuditLogger::UPDATE, ['fields' => $changed]);

            return $sign ? $this->sign($note, $actor) : $note;
        });
    }

    /** Firma una nota en borrador: hash del contenido + quién y cuándo. A partir de aquí es inmutable. */
    public function sign(ClinicalNote $note, OrganizationMember $signer): ClinicalNote
    {
        return DB::transaction(function () use ($note, $signer) {
            $note = $this->lock($note);

            if ($note->isSigned()) {
                throw new DomainException('La nota ya está firmada.');
            }
            if ($note->responsible_doctor_member_id !== $signer->id) {
                throw new DomainException('Solo el doctor responsable de la nota puede firmarla.');
            }
            $this->assertComplete($note);

            // La BD exige los tres datos juntos (CHECK chk_cn_signed_fields).
            $note->update([
                'status' => NoteStatus::Firmada,
                'signed_at' => now(),
                'signed_by_member_id' => $signer->id,
                'content_hash' => $note->computeHash(),
                'signature_mechanism' => self::SIGNATURE_MECHANISM,
            ]);

            $this->audit->log($note, 'sign', ['content_hash' => $note->content_hash]);

            return $note;
        });
    }

    /** Agrega una adenda (versión 1, 2, 3…) a una nota firmada. */
    public function addAddendum(ClinicalNote $note, string $content, string $reason, OrganizationMember $author): ClinicalNoteAddendum
    {
        return DB::transaction(function () use ($note, $content, $reason, $author) {
            $note = $this->lock($note);   // bloquea la nota: dos adendas a la vez no pueden tomar el mismo número

            if (! $note->isSigned()) {
                throw new DomainException('Las adendas son para notas firmadas; un borrador se edita directamente.');
            }

            $previous = $note->addenda()->orderByDesc('version_number')->first();
            $version = ($previous?->version_number ?? 0) + 1;
            $content = trim($content);
            $reason = trim($reason);

            $addendum = ClinicalNoteAddendum::create([
                'note_id' => $note->id,
                'version_number' => $version,
                'content' => $content,
                'reason' => $reason,
                'author_member_id' => $author->id,
                // Encadenado al hash anterior: si se alterara una versión, las siguientes dejarían de cuadrar.
                'content_hash' => hash('sha256', implode('|', [$previous?->content_hash ?? $note->content_hash, $version, $content, $reason, $author->id])),
            ]);

            $this->audit->log($addendum, AuditLogger::INSERT, ['note_id' => $note->id, 'version_number' => $version]);

            return $addendum;
        });
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * La consulta a la que va la nota. Si se eligió una cita, se reutiliza su consulta
     * (varias notas de la misma visita quedan juntas); si no, se crea una con la fecha indicada.
     */
    private function encounterFor(Patient $patient, array $data): ClinicalEncounter
    {
        $record = $this->recordFor($patient);
        $appointment = isset($data['appointment_id']) ? Appointment::findOrFail($data['appointment_id']) : null;

        if ($appointment) {
            $existing = ClinicalEncounter::where('appointment_id', $appointment->id)->first();
            if ($existing) {
                return $existing;
            }
        }

        return ClinicalEncounter::create([
            'medical_record_id' => $record->id,
            'appointment_id' => $appointment?->id,
            'branch_id' => $appointment?->branch_id ?? Branch::where('is_main', true)->value('id'),
            'doctor_member_id' => $appointment?->doctor_member_id ?? $data['doctor_member_id'],
            'occurred_at' => $appointment?->starts_at ?? Carbon::parse($data['occurred_on'])->setTimeFrom(now()),
        ]);
    }

    /** Columnas de contenido según el tipo de nota. En la simple, el texto va en "objective". */
    private function content(array $data): array
    {
        $clean = fn ($value) => filled($value) ? trim($value) : null;
        $soap = ($data['note_type'] ?? null) === NoteType::Soap->value;

        return [
            'treatment_id' => $data['treatment_id'] ?? null,
            'tooth_fdi' => $data['tooth_fdi'] ?? null,
            'subjective' => $soap ? $clean($data['subjective'] ?? null) : null,
            'objective' => $clean($data['objective'] ?? null),
            'analysis' => $soap ? $clean($data['analysis'] ?? null) : null,
            'plan' => $soap ? $clean($data['plan'] ?? null) : null,
        ];
    }

    private function assertCanSign(string $doctorId, OrganizationMember $member, string $errorBag): void
    {
        if ($doctorId !== $member->id) {
            throw ValidationException::withMessages([
                'doctor_member_id' => 'Solo el doctor responsable puede firmar la nota. Guárdala como borrador para que él la firme.',
            ])->errorBag($errorBag);
        }
    }

    /** Para firmar, la nota debe estar completa: la simple con texto; la SOAP con sus 4 secciones. */
    private function assertComplete(ClinicalNote $note): void
    {
        $required = $note->isSoap() ? array_keys(ClinicalNote::SOAP_SECTIONS) : ['objective'];

        foreach ($required as $field) {
            if (blank($note->{$field})) {
                $label = ClinicalNote::SOAP_SECTIONS[$field][0];
                throw new DomainException($note->isSoap()
                    ? "Para firmar la nota completa la sección {$label}."
                    : 'La nota está vacía: describe el procedimiento y los hallazgos antes de firmar.');
            }
        }
    }

    private function lock(ClinicalNote $note): ClinicalNote
    {
        return ClinicalNote::query()->whereKey($note->getKey())->lockForUpdate()->firstOrFail();
    }
}
