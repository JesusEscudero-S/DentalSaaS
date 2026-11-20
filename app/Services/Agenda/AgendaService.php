<?php

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgendaService
{
    public const START_HOUR = 8;     // primera hora del calendario

    public const END_HOUR = 20;      // última hora

    public const HOUR_HEIGHT = 60;   // píxeles por hora (debe coincidir con h-[60px] en la vista)

    public const MIN_CARD_HEIGHT = 54; // altura mínima de una tarjeta: hora + paciente + motivo

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
     * Citas de la semana (sin las canceladas), con paciente y doctor ya cargados.
     * Si se pasa $doctorId, solo las de ese doctor.
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

    /**
     * RF 4.1: agenda una cita nueva.
     * RN-C1 (cero empalmes): el doctor no puede tener otra cita activa que se cruce con este horario.
     *
     * @param  array{patient_id: string, doctor_member_id: string, date: string, start_time: string, duration_minutes: int|string, notes: string}  $data
     */
    public function create(array $data, User $actor): Appointment
    {
        $start = Carbon::parse($data['date'].' '.$data['start_time']);
        $end = $start->copy()->addMinutes((int) $data['duration_minutes']);

        return DB::transaction(function () use ($data, $actor, $start, $end) {
            // 1) ¿El doctor ya tiene una cita que se empalme?
            //    Se empalman si la existente empieza antes de que termine la nueva
            //    Y termina después de que empieza la nueva.
            $clash = Appointment::query()
                ->where('doctor_member_id', $data['doctor_member_id'])
                ->whereIn('status', array_map(fn (AppointmentStatus $s) => $s->value, AppointmentStatus::blocking()))
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->lockForUpdate()   // dos recepcionistas al mismo tiempo: la segunda espera y ve el empalme
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'start_time' => 'Horario no disponible para el odontólogo seleccionado.',
                ])->errorBag('createAppointment');
            }

            // 2) Crear la cita (organization_id lo pone BelongsToOrganization).
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

            // 3) Historial: la cita "nace" en Pendiente (no tenía estado antes).
            $appointment->statusHistory()->create([
                'organization_id' => $appointment->organization_id,
                'from_status' => null,
                'to_status' => AppointmentStatus::Pendiente,
                'actor_user_id' => $actor->id,
            ]);

            return $appointment;
        });
    }
}
