<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\CloseCashSessionRequest;
use App\Http\Requests\Finances\OpenCashSessionRequest;
use App\Http\Requests\Finances\StoreCashAdjustmentRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\FinancialMovement;
use App\Services\Finances\CashSessionService;
use App\Support\Ui;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** RF-07 a RF-09 */
class CashSessionController extends Controller
{
    public function __construct(private CashSessionService $sessions) {}

    public function open(OpenCashSessionRequest $request, CashRegister $register): RedirectResponse
    {
        $session = $this->sessions->open($register, $request->validated('opening_amount'));

        return back()->with('toast', ['type' => 'success', 'title' => 'Caja abierta correctamente', 'message' => "{$register->name} · apertura ".Ui::money($session->opening_amount)]);
    }

    public function show(CashSession $session): View
    {
        $session->load(['register.branch', 'openedBy', 'closedBy']);

        return view('finances.cash.session', [
            'session' => $session,
            'summary' => $this->sessions->summary($session),
            'movements' => FinancialMovement::where('cash_session_id', $session->id)
                ->with(['category', 'creator', 'reversal'])
                ->orderByDesc('created_at')
                ->paginate(8),
            'adjustments' => $session->adjustments()->with('creator')->orderBy('created_at')->get(),
        ]);
    }

    public function close(CloseCashSessionRequest $request, CashSession $session): RedirectResponse
    {
        $closed = $this->sessions->close($session, $request->validated('counted_cash'), $request->validated('notes'));

        return redirect()
            ->route('finances.cash.sessions.show', $closed)
            ->with('toast', ['type' => 'success', 'title' => 'Corte de caja realizado', 'message' => 'Esperado '.Ui::money($closed->expected_cash).' · Contado '.Ui::money($closed->counted_cash)]);
    }

    public function storeAdjustment(StoreCashAdjustmentRequest $request, CashSession $session): RedirectResponse
    {
        $adjustment = $this->sessions->addAdjustment($session, $request->validated('amount'), $request->validated('reason'));

        return back()->with('toast', ['type' => 'success', 'title' => 'Ajuste registrado', 'message' => Ui::money($adjustment->amount)]);
    }
}
