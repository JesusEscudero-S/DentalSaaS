<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreAppointmentRequest;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\RedirectResponse;

/**
 * Operaciones sobre citas (crear; después reagendar, cancelar, iniciar).
 * Ver el calendario es responsabilidad de AgendaController.
 */
class AppointmentController extends Controller
{
    public function __construct(private AgendaService $agenda) {}

    /** RF 4.1 Agendar nueva cita. */
    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = $this->agenda->create($request->validated(), $request->user());

        return redirect()
            ->route('agenda.index', ['semana' => $appointment->starts_at->toDateString()])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Cita programada exitosamente',   // mensaje exacto del PDF
                'message' => $appointment->starts_at->translatedFormat('l j \d\e F, H:i'),
            ]);
    }
}
