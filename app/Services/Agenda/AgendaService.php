<?php

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgendaService
{
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
        // 1) solo las que empiezan dentro de la semana
        // 2) sin canceladas
        // 3) cargar relaciones
        // 4) ordenar
        // 5) traer los resultados
    }
}
