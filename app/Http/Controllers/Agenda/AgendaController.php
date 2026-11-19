<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(5);

        return view('agenda.index', ['weekStart' => $weekStart, 'weekEnd' => $weekEnd]);
    }
}
