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
     * Calendario. Todo el estado vive en la URL (todos los parámetros son opcionales):
     *   /agenda?semana=2026-11-23&doctor=<uuid>          vista Semana (lunes a sábado)
     *   /agenda?vista=dia&fecha=2026-11-19&doctor=<uuid>  vista Día
     */
    public function index(Request $request): View
    {
        // 1) Validar lo que llega en la URL: nunca confiar en ella.
        $filters = $request->validate([
            'vista' => ['nullable', 'in:semana,dia'],
            'semana' => ['nullable', 'date'],
            'fecha' => ['nullable', 'date'],
            'doctor' => ['nullable', 'uuid'],
        ]);

        $view = $filters['vista'] ?? 'semana';
        $doctorId = $filters['doctor'] ?? null;

        // 2) Rango de fechas según la vista.
        if ($view === 'dia') {
            $rangeStart = Carbon::parse($filters['fecha'] ?? 'today')->startOfDay();
            $rangeEnd = $rangeStart->copy()->endOfDay();
            $days = [$rangeStart];
            $prev = $rangeStart->copy()->subDay();
            $next = $rangeStart->copy()->addDay();
        } else {
            $rangeStart = Carbon::parse($filters['semana'] ?? 'today')->startOfWeek();
            $rangeEnd = $rangeStart->copy()->addDays(5)->endOfDay();   // sábado 23:59:59
            $days = $this->agenda->weekDays($rangeStart);
            $prev = $rangeStart->copy()->subWeek();
            $next = $rangeStart->copy()->addWeek();
        }

        // 3) Citas del rango (filtradas por doctor si se eligió uno), agrupadas por día.
        $appointmentsByDay = $this->agenda
            ->weekAppointments($rangeStart, $rangeEnd, $doctorId)
            ->groupBy(fn ($appointment) => $appointment->starts_at->toDateString());   // "2026-11-16"

        $doctors = $this->agenda->doctors();

        // 4) Enlaces de la barra. Se arman aquí para que la vista solo los use.
        //    Un parámetro en null no aparece en la URL.
        $url = function (Carbon $date, ?string $mode = null, ?string $doctor = null) use ($view): string {
            $mode ??= $view;   // si no se indica, la vista actual

            $params = $mode === 'dia'
                ? ['vista' => 'dia', 'fecha' => $date->toDateString()]
                : ['semana' => $date->toDateString()];

            return route('agenda.index', [...$params, 'doctor' => $doctor]);
        };

        return view('agenda.index', [
            'view' => $view,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'days' => $days,
            'hours' => $this->agenda->hours(),
            'appointmentsByDay' => $appointmentsByDay,

            // Barra de navegación y filtro
            'doctors' => $doctors,
            'doctorColors' => $this->agenda->doctorColors($doctors),
            'doctorId' => $doctorId,
            'prevUrl' => $url($prev, doctor: $doctorId),
            'nextUrl' => $url($next, doctor: $doctorId),
            'todayUrl' => $url(today(), doctor: $doctorId),
            'weekViewUrl' => $url($rangeStart, 'semana', $doctorId),
            'dayViewUrl' => $url($view === 'dia' ? $rangeStart : ($rangeStart->isSameWeek(today()) ? today() : $rangeStart), 'dia', $doctorId),
            'doctorUrl' => fn (?string $doctor) => $url($rangeStart, doctor: $doctor),

            // Modal "Nueva cita"
            'patients' => $this->agenda->activePatients(),
        ]);
    }
}
