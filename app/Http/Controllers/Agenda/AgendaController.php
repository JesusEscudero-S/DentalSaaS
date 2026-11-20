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
     *   /agenda?vista=mes&mes=2026-11&doctor=<uuid>       vista Mes
     */
    public function index(Request $request): View
    {
        // 1) Validar lo que llega en la URL: nunca confiar en ella.
        $filters = $request->validate([
            'vista' => ['nullable', 'in:semana,dia,mes'],
            'semana' => ['nullable', 'date'],
            'fecha' => ['nullable', 'date'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'doctor' => ['nullable', 'uuid'],
        ]);

        $view = $filters['vista'] ?? 'semana';
        $doctorId = $filters['doctor'] ?? null;

        // 2) Rango de fechas según la vista. $anchor es la fecha "dueña" de la vista
        //    (el día, el lunes de la semana o el día 1 del mes): con ella se arman los enlaces.
        if ($view === 'dia') {
            $anchor = Carbon::parse($filters['fecha'] ?? 'today')->startOfDay();
            $rangeStart = $anchor->copy();
            $rangeEnd = $anchor->copy()->endOfDay();
            $days = [$anchor];
            [$prev, $next] = [$anchor->copy()->subDay(), $anchor->copy()->addDay()];
        } elseif ($view === 'mes') {
            $anchor = isset($filters['mes'])
                ? Carbon::createFromFormat('Y-m-d', $filters['mes'].'-01')->startOfDay()
                : today()->startOfMonth();
            // La cuadrícula empieza el lunes de la semana del día 1 y termina el domingo de la semana del último día.
            $rangeStart = $anchor->copy()->startOfWeek();
            $rangeEnd = $anchor->copy()->endOfMonth()->endOfWeek();
            $days = $this->agenda->monthWeeks($anchor);
            [$prev, $next] = [$anchor->copy()->subMonthNoOverflow(), $anchor->copy()->addMonthNoOverflow()];
        } else {
            $anchor = Carbon::parse($filters['semana'] ?? 'today')->startOfWeek();
            $rangeStart = $anchor->copy();
            $rangeEnd = $anchor->copy()->addDays(5)->endOfDay();   // sábado 23:59:59
            $days = $this->agenda->weekDays($anchor);
            [$prev, $next] = [$anchor->copy()->subWeek(), $anchor->copy()->addWeek()];
        }

        // Al cambiar de vista (Día / Semana / Mes) se va a "hoy" si hoy está en lo que se ve; si no, a $anchor.
        $todayIsVisible = $view === 'mes' ? today()->isSameMonth($anchor) : today()->between($rangeStart, $rangeEnd);
        $focus = $todayIsVisible ? today() : $anchor;

        // 3) Citas del rango (filtradas por doctor si se eligió uno), agrupadas por día.
        $appointmentsByDay = $this->agenda
            ->weekAppointments($rangeStart, $rangeEnd, $doctorId)
            ->groupBy(fn ($appointment) => $appointment->starts_at->toDateString());   // "2026-11-16"

        $doctors = $this->agenda->doctors();

        // 4) Enlaces de la barra. Se arman aquí para que la vista solo los use.
        //    Un parámetro en null no aparece en la URL.
        $url = function (Carbon $date, ?string $mode = null, ?string $doctor = null) use ($view): string {
            $mode ??= $view;   // si no se indica, la vista actual

            $params = match ($mode) {
                'dia' => ['vista' => 'dia', 'fecha' => $date->toDateString()],
                'mes' => ['vista' => 'mes', 'mes' => $date->format('Y-m')],
                default => ['semana' => $date->toDateString()],
            };

            return route('agenda.index', [...$params, 'doctor' => $doctor]);
        };

        return view('agenda.index', [
            'view' => $view,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'anchor' => $anchor,
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
            'dayViewUrl' => $url($focus, 'dia', $doctorId),
            'weekViewUrl' => $url($focus, 'semana', $doctorId),
            'monthViewUrl' => $url($focus, 'mes', $doctorId),
            'dayUrl' => fn (Carbon $day) => $url($day, 'dia', $doctorId),   // vista Mes: clic en un día
            'doctorUrl' => fn (?string $doctor) => $url($anchor, doctor: $doctor),

            // Modal "Nueva cita"
            'patients' => $this->agenda->activePatients(),
        ]);
    }
}
