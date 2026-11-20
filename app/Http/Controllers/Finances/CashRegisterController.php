<?php

namespace App\Http\Controllers\Finances;

use App\Enums\CashSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\CashRegisterRequest;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\Finances\CashRegisterService;
use App\Services\Finances\CashSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Pantalla Caja: tarjetas por caja e historial de cortes (RF-06). */
class CashRegisterController extends Controller
{
    public function __construct(
        private CashRegisterService $registers,
        private CashSessionService $sessions,
    ) {}

    public function index(): View
    {
        $registers = CashRegister::with(['branch', 'openSession.openedBy'])->orderBy('name')->get();

        $expected = $registers
            ->filter(fn (CashRegister $r) => $r->openSession)
            ->mapWithKeys(fn (CashRegister $r) => [$r->openSession->id => $this->sessions->summary($r->openSession)['expected']]);

        $history = CashSession::where('status', CashSessionStatus::Closed->value)
            ->with(['register.branch', 'openedBy', 'closedBy'])
            ->withSum('adjustments', 'amount')
            ->orderByDesc('closed_at')
            ->paginate(8);

        return view('finances.cash.index', [
            'registers' => $registers,
            'expected' => $expected,
            'history' => $history,
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(),
        ]);
    }

    public function store(CashRegisterRequest $request): RedirectResponse
    {
        $register = $this->registers->create($request->validated());

        return redirect()
            ->route('finances.cash.index')
            ->with('toast', ['type' => 'success', 'title' => 'Caja creada', 'message' => $register->name]);
    }

    public function update(CashRegisterRequest $request, CashRegister $register): RedirectResponse
    {
        $this->registers->rename($register, $request->validated('name'));

        return back()->with('toast', ['type' => 'success', 'title' => 'Caja actualizada', 'message' => $register->name]);
    }

    public function deactivate(CashRegister $register): RedirectResponse
    {
        $this->registers->deactivate($register);

        return back()->with('toast', ['type' => 'success', 'title' => 'Caja desactivada', 'message' => $register->name]);
    }

    public function reactivate(CashRegister $register): RedirectResponse
    {
        $this->registers->reactivate($register);

        return back()->with('toast', ['type' => 'success', 'title' => 'Caja reactivada', 'message' => $register->name]);
    }
}
