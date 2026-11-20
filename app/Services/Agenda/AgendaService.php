<?php

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     */
    public function weekAppointments(Carbon $start, Carbon $end): Collection
    {
        return Appointment::query()
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', '!=', AppointmentStatus::Cancelada->value)
            ->with(['patient', 'doctor.user'])
            ->orderBy('starts_at')
            ->get();
    }
}
