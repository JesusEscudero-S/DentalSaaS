<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\CancelAppointmentRequest;
use App\Http\Requests\Agenda\RescheduleAppointmentRequest;
use App\Http\Requests\Agenda\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Operaciones sobre citas: crear, reagendar, cancelar, iniciar, finalizar y "No asistió".
 * Ver el calendario es responsabilidad de AgendaController.
 *
 * El {appointment} de la URL se resuelve con route model binding: el filtro por clínica
 * (BelongsToOrganization) hace que una cita de otra clínica dé 404.
 */
class AppointmentController extends Controller
{
    public function __construct(private AgendaService $agenda) {}

    /** RF 4.1 Agendar nueva cita. */
    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = $this->agenda->create($request->validated(), $request->user());

        return $this->backToWeek($appointment, 'Cita programada exitosamente');   // mensaje exacto del PDF
    }

    /** RF 4.3 Reprogramar cita. */
    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $appointment) {
            $appointment = $this->agenda->reschedule($appointment, $request->validated(), $request->user());

            return $this->backToWeek($appointment, 'Cita actualizada correctamente');   // mensaje exacto del PDF
        });
    }

    /** RF 4.4 Cancelar cita (con motivo). */
    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $appointment) {
            $this->agenda->cancel($appointment, $request->validated('reason'), $request->user());

            return back()->with('toast', $this->toast('Estatus de cita cambiado a Cancelada', $appointment));   // mensaje del PDF
        });
    }

    /** Iniciar la cita (Figma: "¿Iniciar la cita?"). */
    public function start(Request $request, Appointment $appointment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $appointment) {
            $this->agenda->start($appointment, $request->user());

            return back()->with('toast', $this->toast('Cita iniciada', $appointment));
        });
    }

    /** Finalizar la cita en curso. */
    public function finish(Request $request, Appointment $appointment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $appointment) {
            $this->agenda->finish($appointment, $request->user());

            return back()->with('toast', $this->toast('Cita finalizada', $appointment));
        });
    }

    /** RN-C5: marcar "No asistió". */
    public function noShow(Request $request, Appointment $appointment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $appointment) {
            $this->agenda->markNoShow($appointment, $request->user());

            return back()->with('toast', $this->toast('Cita marcada como No asistió', $appointment));
        });
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Ejecuta la acción; si la máquina de estados la rechaza (DomainException),
     * regresa con un toast de error en vez de mostrar una página de error.
     */
    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $e->getMessage()]);
        }
    }

    /** Regresa al calendario en la semana de la cita. */
    private function backToWeek(Appointment $appointment, string $title): RedirectResponse
    {
        return redirect()
            ->route('agenda.index', ['semana' => $appointment->starts_at->toDateString()])
            ->with('toast', $this->toast($title, $appointment));
    }

    /** @return array{type: string, title: string, message: string} */
    private function toast(string $title, Appointment $appointment): array
    {
        return [
            'type' => 'success',
            'title' => $title,
            'message' => $appointment->patient->full_name.' · '.$appointment->starts_at->translatedFormat('l j \d\e F, H:i'),
        ];
    }
}
