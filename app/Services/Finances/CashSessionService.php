<?php

namespace App\Services\Finances;

use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionAdjustment;
use App\Models\FinancialMovement;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** RF-07 a RF-09: apertura, corte y ajustes de caja. */
class CashSessionService
{
    public function __construct(
        private AuditLogger $audit,
        private FinanceReportService $reports,
    ) {}

    /** RF-07: una sola sesión abierta por caja (índice único uq_cash_one_open). */
    public function open(CashRegister $register, string $openingAmount): CashSession
    {
        try {
            return DB::transaction(function () use ($register, $openingAmount) {
                $locked = CashRegister::whereKey($register->getKey())->lockForUpdate()->firstOrFail();
                if (! $locked->isActive()) {
                    throw new BusinessRuleException('No se puede abrir una caja inactiva.');
                }

                $session = CashSession::create([
                    'cash_register_id' => $locked->id,
                    'opened_by' => Auth::id(),
                    'opened_at' => now(),
                    'opening_amount' => $openingAmount,
                ]);

                $this->audit->log($session, AuditLogger::INSERT, ['cash_register_id' => $locked->id, 'opening_amount' => $session->opening_amount]);

                return $session;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new BusinessRuleException('Esta caja ya tiene una sesión abierta');
            }
            throw BusinessRuleException::fromQueryException($e) ?? $e;
        }
    }

    /**
     * RF-08: esperado = apertura + Σ ingresos en efectivo − Σ egresos en efectivo, sin movimientos
     * revertidos ni sus reversiones (RF-04).
     *
     * @return array{opening: string, cash_in: string, cash_out: string, expected: string}
     */
    public function summary(CashSession $session): array
    {
        $totals = $this->reports->totals(
            FinancialMovement::where('cash_session_id', $session->id)->where('method', PaymentMethod::Cash->value)
        );
        $opening = bcadd((string) $session->opening_amount, '0', 2);

        return [
            'opening' => $opening,
            'cash_in' => $totals['income'],
            'cash_out' => $totals['expense'],
            'expected' => bcsub(bcadd($opening, $totals['income'], 2), $totals['expense'], 2),
        ];
    }

    /**
     * RF-08: el corte se guarda con un solo UPDATE porque, ya cerrada, el trigger cash_sessions_closed_bu
     * bloquea cualquier cambio. El esperado se recalcula aquí, con la sesión bloqueada.
     */
    public function close(CashSession $session, string $countedCash, ?string $notes): CashSession
    {
        try {
            return DB::transaction(function () use ($session, $countedCash, $notes) {
                $locked = CashSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                if (! $locked->isOpen()) {
                    throw new BusinessRuleException('Esta caja ya fue cortada.');
                }

                $expected = $this->summary($locked)['expected'];
                $difference = bcsub($countedCash, $expected, 2);

                if (bccomp($difference, '0', 2) !== 0 && blank($notes)) {
                    throw ValidationException::withMessages([
                        'notes' => 'Explica la diferencia entre el efectivo contado y el esperado.',
                    ])->errorBag('closeSession');
                }

                $locked->update([
                    'status' => CashSessionStatus::Closed,
                    'closed_by' => Auth::id(),
                    'closed_at' => now(),
                    'expected_cash' => $expected,
                    'counted_cash' => $countedCash,
                    'difference' => $difference,
                    'notes' => $notes,
                ]);

                $this->audit->log($locked, AuditLogger::UPDATE, [
                    'status' => [CashSessionStatus::Open->value, CashSessionStatus::Closed->value],
                    'expected_cash' => $expected,
                    'counted_cash' => $locked->counted_cash,
                    'difference' => $difference,
                ]);

                return $locked;
            });
        } catch (QueryException $e) {
            throw BusinessRuleException::fromQueryException($e) ?? $e;
        }
    }

    /** RF-09: las correcciones a una caja cortada son ajustes con signo. */
    public function addAdjustment(CashSession $session, string $amount, string $reason): CashSessionAdjustment
    {
        return DB::transaction(function () use ($session, $amount, $reason) {
            $locked = CashSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->isOpen()) {
                throw new BusinessRuleException('Los ajustes solo se registran en cajas ya cortadas.');
            }

            $adjustment = CashSessionAdjustment::create([
                'cash_session_id' => $locked->id,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            $this->audit->log($adjustment, AuditLogger::INSERT, ['cash_session_id' => $locked->id, 'amount' => $adjustment->amount]);

            return $adjustment;
        });
    }
}
