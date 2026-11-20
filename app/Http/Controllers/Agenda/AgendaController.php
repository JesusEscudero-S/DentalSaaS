<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private AgendaService $agenda) {}

    /**
     * Calendario semanal. La semana y el doctor llegan por la URL:
     * /agenda?semana=2026-11-23&doctor=<uuid>  (ambos opcionales).
     */
    public function index(Request $request): View
    {
        // 1) Validar lo que llega en la URL: nunca confiar en ella.
        $filters = $request->validate([
            'semana' => ['nullable', 'date'],
            'doctor' => ['nullable', 'uuid'],
        ]);

        // 2) Semana a mostrar: la de la URL o la actual, siempre desde su lunes.
        $weekStart = Carbon::parse($filters['semana'] ?? 'today')->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay();   // sábado 23:59:59
        $doctorId = $filters['doctor'] ?? null;

        // 3) Citas de la semana (filtradas por doctor si se eligió uno), agrupadas por día.
        $appointmentsByDay = $this->agenda
            ->weekAppointments($weekStart, $weekEnd, $doctorId)
            ->groupBy(fn ($appointment) => $appointment->starts_at->toDateString());   // "2026-11-16"

        $doctors = $this->agenda->doctors();

        return view('agenda.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $this->agenda->weekDays($weekStart),
            'hours' => $this->agenda->hours(),
            'appointmentsByDay' => $appointmentsByDay,

            // Barra de navegación y filtro
            'doctors' => $doctors,
            'doctorColors' => $this->agenda->doctorColors($doctors),
            'doctorId' => $doctorId,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),

            // Modal "Nueva cita"
            'patients' => $this->agenda->activePatients(),
        ]);
    }
}
