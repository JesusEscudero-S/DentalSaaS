<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private AgendaService $agenda) {}

    public function index(Request $request): View
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay();
        $appointments = $this->agenda->weekAppointments($weekStart, $weekEnd);

        return view('agenda.index', ['weekStart' => $weekStart, 'weekEnd' => $weekEnd, 'appointments' => $appointments]);
    }
}
