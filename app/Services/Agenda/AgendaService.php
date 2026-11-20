<?php

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lógica del módulo 4 (Agenda y citas): armar el calendario y operar citas.
 */
class AgendaService
{
    public const START_HOUR = 8;     // primera hora del calendario

    public const END_HOUR = 20;      // última hora

    public const HOUR_HEIGHT = 60;   // píxeles por hora (debe coincidir con h-[60px] en la vista)

    public const MIN_CARD_HEIGHT = 54; // altura mínima de una tarjeta: hora + paciente + motivo

    public function __construct(private AuditLogger $audit) {}

    // ─────────────────────────────────────────────────────────────
    // Calendario
    // ─────────────────────────────────────────────────────────────

    /**
     * Los 6 días de la semana (lunes a sábado) a partir del lunes recibido.
     *
     * @return array<int, Carbon>
     */
    public function weekDays(Carbon $start): array
    {
        $days = [];

        for ($i = 0; $i <= 5; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        return $days;
    }

    /**
     * Horas que muestra el calendario: [8, 9, ..., 20].
     *
     * @return array<int, int>
     */
    public function hours(): array
    {
        return range(self::START_HOUR, self::END_HOUR);
    }

    /**
     * Posición de la tarjeta de una cita dentro de la columna del día.
     * top = minutos desde las 8:00 × píxeles por minuto; height = duración × píxeles por minuto.
     *
     * @return array{top: float, height: float}
     */
    public static function position(Appointment $appointment): array
    {
        $pxPerMinute = self::HOUR_HEIGHT / 60;

        $minutesFromStart = ($appointment->starts_at->hour - self::START_HOUR) * 60 + $appointment->starts_at->minute;
        $duration = $appointment->starts_at->diffInMinutes($appointment->ends_at);

        return [
            'top' => round($minutesFromStart * $pxPerMinute),
            // Una cita corta (30 min) mediría 30 px y no le cabría el nombre: se usa una altura mínima.
            'height' => max(round($duration * $pxPerMinute), self::MIN_CARD_HEIGHT),
        ];
    }

    /**
     * Citas entre dos fechas (una semana o un día), sin las canceladas: RF 4.4 dice que
     * una cita cancelada libera su espacio en el calendario. Si se pasa $doctorId, solo las suyas.
     */
    public function weekAppointments(Carbon $start, Carbon $end, ?string $doctorId = null): Collection
    {
        return Appointment::query()
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', '!=', AppointmentStatus::Cancelada->value)
            ->when($doctorId, fn ($query) => $query->where('doctor_member_id', $doctorId))
            ->with(['patient', 'doctor.user'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Doctores activos de la clínica (para los chips del filtro), ordenados por nombre.
     */
    public function doctors(): Collection
    {
        return OrganizationMember::doctors()
            ->with('user')
            ->get()
            ->sortBy(fn ($doctor) => $doctor->displayName())
            ->values();
    }

    /**
     * Un color distinto por doctor, en orden: [member_id => 'blue', ...].
     * Lo usan los chips del filtro y las tarjetas, así ambos coinciden.
     *
     * @return array<string, string>
     */
    public function doctorColors(Collection $doctors): array
    {
        $palette = ['blue', 'sky', 'green', 'violet', 'amber'];

        return $doctors
            ->values()
            ->mapWithKeys(fn ($doctor, $i) => [$doctor->id => $palette[$i % count($palette)]])
            ->all();
    }

    /** Pacientes activos para el select del modal, ordenados por nombre. */
    public function activePatients(): Collection
    {
        return Patient::active()->orderBy('full_name')->get(['id', 'full_name', 'folio']);
    }

    // ─────────────────────────────────────────────────────────────
    // Operaciones sobre citas
    // ─────────────────────────────────────────────────────────────

    /**
     * RF 4.1: agenda una cita nueva.
     * RN-C1 (cero empalmes): el doctor no puede tener otra cita activa que se cruce con este horario.
     *
     * @param  array{patient_id: string, doctor_member_id: string, date: string, start_time: string, duration_minutes: int|string, notes: string}  $data
     */
    public function create(array $data, User $actor): Appointment
    {
        [$start, $end] = $this->range($data);

        return DB::transaction(function () use ($data, $actor, $start, $end) {
            $this->assertNoClash($data['doctor_member_id'], $start, $end, 'createAppointment');

            // organization_id lo pone BelongsToOrganization.
            $appointment = Appointment::create([
                'branch_id' => Branch::where('is_main', true)->value('id'),
                'patient_id' => $data['patient_id'],
                'doctor_member_id' => $data['doctor_member_id'],
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => AppointmentStatus::Pendiente,
                'notes' => trim($data['notes']),
                'created_by' => $actor->id,
            ]);

            // La cita "nace" en Pendiente: no tenía estado antes.
            $this->recordHistory($appointment, null, AppointmentStatus::Pendiente, $actor);
            $this->audit->log($appointment, AuditLogger::INSERT, ['starts_at' => $start->toDateTimeString()]);

            return $appointment;
        });
    }

    /**
     * RF 4.3: reprograma una cita (nueva fecha, hora, duración y opcionalmente otro doctor).
     * Aplica RN-C1 contra las demás citas, ignorando la propia.
     *
     * @param  array{doctor_member_id: string, date: string, start_time: string, duration_minutes: int|string}  $data
     */
    public function reschedule(Appointment $appointment, array $data, User $actor): Appointment
    {
        [$start, $end] = $this->range($data);

        return DB::transaction(function () use ($appointment, $data, $actor, $start, $end) {
            $appointment = $this->lock($appointment);

            if (! $appointment->status->canReschedule()) {
                throw new DomainException('Solo se pueden reagendar citas pendientes o confirmadas.');
            }

            $this->assertNoClash($data['doctor_member_id'], $start, $end, 'rescheduleAppointment', ignoreId: $appointment->id);

            $oldStart = $appointment->starts_at->copy();

            $appointment->update([
                'doctor_member_id' => $data['doctor_member_id'],
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            // RN-C4: el estado no cambia, pero queda la traza de la fecha anterior y la nueva.
            $this->recordHistory($appointment, $appointment->status, $appointment->status, $actor, oldStart: $oldStart, newStart: $start);
            $this->audit->log($appointment, AuditLogger::UPDATE, ['fields' => ['starts_at', 'ends_at', 'doctor_member_id']]);

            return $appointment;
        });
    }

    /** Iniciar: el paciente pasa al consultorio (pendiente/confirmada → en curso). */
    public function start(Appointment $appointment, User $actor): Appointment
    {
        return $this->changeStatus($appointment, AppointmentStatus::EnCurso, $actor);
    }

    /** Finalizar: termina la consulta (en curso → completada). */
    public function finish(Appointment $appointment, User $actor): Appointment
    {
        return $this->changeStatus($appointment, AppointmentStatus::Completada, $actor);
    }

    /** RF 4.4 / RN-C3: cancelar (baja lógica) con motivo obligatorio. Libera el horario. */
    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        return $this->changeStatus($appointment, AppointmentStatus::Cancelada, $actor, trim($reason));
    }

    /** RN-C5: marcar "No asistió", solo si la cita ya tiene más de 30 minutos de retraso. */
    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        if (! $appointment->isLate()) {
            throw new DomainException('Solo se puede marcar "No asistió" 30 minutos después de la hora de la cita.');
        }

        return $this->changeStatus($appointment, AppointmentStatus::NoAsistio, $actor);
    }

    // ─────────────────────────────────────────────────────────────
    // Piezas internas (las comparten varias operaciones)
    // ─────────────────────────────────────────────────────────────

    /**
     * Cambio de estado validado por la máquina de estados del enum, con historial y bitácora.
     * Si el cambio no está permitido (por ejemplo, iniciar una cita ya cancelada) lanza DomainException.
     */
    private function changeStatus(Appointment $appointment, AppointmentStatus $to, User $actor, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $to, $actor, $reason) {
            // Se vuelve a leer con bloqueo: si otra persona la cambió hace un segundo, vemos el estado real.
            $appointment = $this->lock($appointment);
            $from = $appointment->status;

            if (! $from->canTransitionTo($to)) {
                throw new DomainException("No se puede pasar una cita de «{$from->label()}» a «{$to->label()}».");
            }

            $appointment->update(['status' => $to]);   // updated_at se actualiza solo (RN-C4)

            $this->recordHistory($appointment, $from, $to, $actor, reason: $reason);
            $this->audit->log($appointment, AuditLogger::UPDATE, ['status' => [$from->value, $to->value]]);

            return $appointment;
        });
    }

    /**
     * RN-C1: ¿el doctor tiene otra cita activa que se cruce con [$start, $end)?
     * Dos citas se empalman si una empieza antes de que termine la otra Y termina después de que la otra empieza.
     * Con lockForUpdate(), si dos personas agendan al mismo tiempo, la segunda espera y sí ve el empalme.
     */
    private function assertNoClash(string $doctorId, Carbon $start, Carbon $end, string $errorBag, ?string $ignoreId = null): void
    {
        $clash = Appointment::query()
            ->where('doctor_member_id', $doctorId)
            ->whereIn('status', AppointmentStatus::blockingValues())
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))   // al reagendar, no chocar consigo misma
            ->lockForUpdate()
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'start_time' => 'Horario no disponible para el odontólogo seleccionado.',
            ])->errorBag($errorBag);
        }
    }

    /** RN-C4: una fila en appointment_status_history por cada cambio. */
    private function recordHistory(
        Appointment $appointment,
        ?AppointmentStatus $from,
        AppointmentStatus $to,
        User $actor,
        ?string $reason = null,
        ?Carbon $oldStart = null,
        ?Carbon $newStart = null,
    ): void {
        $appointment->statusHistory()->create([
            'organization_id' => $appointment->organization_id,
            'from_status' => $from,
            'to_status' => $to,
            'old_starts_at' => $oldStart,
            'new_starts_at' => $newStart,
            'reason' => $reason,
            'actor_user_id' => $actor->id,
        ]);
    }

    /** Vuelve a leer la cita bloqueando su fila hasta que termine la transacción. */
    private function lock(Appointment $appointment): Appointment
    {
        return Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Inicio y fin a partir de fecha + hora + duración del formulario.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(array $data): array
    {
        $start = Carbon::parse($data['date'].' '.$data['start_time']);

        return [$start, $start->copy()->addMinutes((int) $data['duration_minutes'])];
    }
}
