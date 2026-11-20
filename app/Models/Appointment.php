<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cita (módulo 4. Agenda y citas).
 */
class Appointment extends Model
{
    use BelongsToOrganization, HasUuids;

    /** RN-C5: minutos de tolerancia antes de considerar que el paciente llega tarde. */
    public const LATE_AFTER_MINUTES = 30;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'patient_id',
        'doctor_member_id',
        'treatment_id',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => AppointmentStatus::class,
    ];

    // Relaciones

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'doctor_member_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }

    // Reglas de la cita

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * RN-C5: pasaron más de 30 minutos de la hora de inicio y la cita sigue sin iniciarse
     * (pendiente o confirmada). Se pinta en rojo y se habilita "No asistió".
     */
    public function isLate(): bool
    {
        return in_array($this->status, [AppointmentStatus::Pendiente, AppointmentStatus::Confirmada], true)
            && now()->greaterThan($this->starts_at->copy()->addMinutes(self::LATE_AFTER_MINUTES));
    }

    /**
     * Datos para los modales (detalle, reagendar, cancelar, iniciar).
     * Se mandan como "payload" al abrir el modal desde la tarjeta del calendario.
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'patient' => "{$this->patient->full_name} ({$this->patient->folio})",
            'patientName' => $this->patient->full_name,
            'reason' => $this->notes,
            'doctor' => $this->doctor->displayName(),
            'date' => $this->starts_at->translatedFormat('l j M Y · H:i'),
            'time' => $this->starts_at->format('H:i'),
            'duration' => $this->durationMinutes().' min',
            'status' => $status->label(),
            'late' => $this->isLate(),

            // Qué botones se muestran (máquina de estados del enum)
            'canReschedule' => $status->canReschedule(),
            'canConfirm' => $status->canTransitionTo(AppointmentStatus::Confirmada),
            'canStart' => $status->canTransitionTo(AppointmentStatus::EnCurso),
            'canFinish' => $status->canTransitionTo(AppointmentStatus::Completada),
            'canCancel' => $status->canTransitionTo(AppointmentStatus::Cancelada),
            'canNoShow' => $this->isLate(),

            // Valores para precargar el modal "Reagendar"
            'form' => [
                'doctor_member_id' => $this->doctor_member_id,
                'date' => $this->starts_at->toDateString(),
                'start_time' => $this->starts_at->format('H:i'),
                'duration_minutes' => (string) $this->durationMinutes(),
            ],

            // A dónde envía cada botón
            'urls' => [
                'reschedule' => route('agenda.appointments.reschedule', $this),
                'confirm' => route('agenda.appointments.confirm', $this),
                'cancel' => route('agenda.appointments.cancel', $this),
                'start' => route('agenda.appointments.start', $this),
                'finish' => route('agenda.appointments.finish', $this),
                'noShow' => route('agenda.appointments.no-show', $this),
                // Módulo 6: si la consulta ya empezó o terminó, abre una nota SOAP ligada a esta cita;
                // si no, el perfil del paciente.
                'record' => in_array($status, [AppointmentStatus::EnCurso, AppointmentStatus::Completada], true)
                    ? route('records.notes.create', [$this->patient_id, 'cita' => $this->id])
                    : route('patients.show', $this->patient_id),
            ],
        ];
    }
}
