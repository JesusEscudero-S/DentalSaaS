<?php

namespace App\Services\Finances;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinancialMovementService
{
    public const PATIENT_PAYMENTS_CATEGORY = 'Pagos de pacientes';

    private const FIELDS = ['direction', 'category_id', 'concept', 'occurred_on', 'amount', 'method', 'branch_id', 'cash_session_id'];

    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * RF-01 / RF-02: registra un ingreso o egreso. El efectivo queda ligado a una sesión de caja abierta.
     *
     * @param  array<string, mixed>  $data  datos validados por StoreFinancialMovementRequest
     */
    public function record(array $data, ?UploadedFile $receipt = null): FinancialMovement
    {
        $movement = new FinancialMovement(Arr::only($data, self::FIELDS));
        $movement->id = $movement->newUniqueId();
        $movement->created_by = Auth::id();
        if ($movement->method !== PaymentMethod::Cash) {
            $movement->cash_session_id = null;
        }

        $path = $receipt ? $this->storeReceipt($movement, $receipt) : null;

        try {
            return DB::transaction(function () use ($movement, $path) {
                if ($movement->cash_session_id) {
                    $this->lockOpenSession($movement->cash_session_id);
                }

                $movement->receipt_storage_key = $path;
                $movement->save();

                $this->audit->log($movement, AuditLogger::INSERT, [
                    'direction' => $movement->direction->value,
                    'amount' => $movement->amount,
                    'method' => $movement->method->value,
                    'category_id' => $movement->category_id,
                ]);

                return $movement;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $e instanceof QueryException ? (BusinessRuleException::fromQueryException($e) ?? $e) : $e;
        }
    }

    /**
     * RF-03 (D4): los movimientos son inmutables; se corrigen con una reversión que apunta al original.
     */
    public function reverse(FinancialMovement $movement, string $reason): FinancialMovement
    {
        try {
            return DB::transaction(function () use ($movement, $reason) {
                $original = FinancialMovement::whereKey($movement->getKey())->lockForUpdate()->firstOrFail();
                $original->setRelation('reversal', FinancialMovement::where('reversed_of_id', $original->id)->lockForUpdate()->first());
                if ($original->cash_session_id) {
                    $original->setRelation('cashSession', CashSession::whereKey($original->cash_session_id)->lockForUpdate()->first());
                }

                if ($blocker = $original->reversalBlocker()) {
                    throw new BusinessRuleException($blocker);
                }

                $reversal = FinancialMovement::create([
                    'branch_id' => $original->branch_id,
                    'direction' => $original->direction,
                    'category_id' => $original->category_id,
                    'concept' => "Reversión: {$reason}",
                    'occurred_on' => today(),
                    'amount' => $original->amount,
                    'currency' => $original->currency,
                    'method' => $original->method,
                    'cash_session_id' => $original->cash_session_id,
                    'created_by' => Auth::id(),
                    'reversed_of_id' => $original->id,
                ]);

                $this->audit->log($original, 'reverse', ['reversal_id' => $reversal->id]);
                $this->audit->log($reversal, AuditLogger::INSERT, ['reversed_of_id' => $original->id, 'amount' => $reversal->amount]);

                return $reversal;
            });
        } catch (QueryException $e) {
            throw BusinessRuleException::fromQueryException($e) ?? $e;
        }
    }

    /**
     * RF-10 (D7): ingreso generado por un pago de paciente. Lo llama el módulo 9 (Pagos) después de
     * guardar el pago. Es idempotente: si el pago ya tiene movimiento, devuelve ese mismo.
     * El movimiento resultante es de solo lectura en Finanzas (se revierte desde Pagos).
     */
    public function recordFromPayment(Payment $payment): FinancialMovement
    {
        return DB::transaction(function () use ($payment) {
            $existing = FinancialMovement::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }

            $category = FinancialCategory::firstOrCreate([
                'organization_id' => $payment->organization_id,
                'name' => self::PATIENT_PAYMENTS_CATEGORY,
                'direction' => MovementDirection::Income->value,
            ]);
            $patient = $payment->patient()->firstOrFail();

            try {
                $movement = FinancialMovement::create([
                    'organization_id' => $payment->organization_id,
                    'branch_id' => $payment->branch_id,
                    'direction' => MovementDirection::Income,
                    'category_id' => $category->id,
                    'concept' => "Pago de {$patient->full_name} ({$patient->folio})",
                    'occurred_on' => $payment->paid_at->toDateString(),
                    'amount' => $payment->amount,
                    'currency' => $payment->currency ?? 'MXN',
                    'method' => $payment->method,
                    'payment_id' => $payment->id,
                    'cash_session_id' => $payment->method === PaymentMethod::Cash ? $payment->cash_session_id : null,
                    'created_by' => $payment->created_by ?? Auth::id(),
                ]);
            } catch (QueryException $e) {
                // Otro proceso registró el mismo pago al mismo tiempo (uq_fin_movements_org_payment).
                if (($e->errorInfo[1] ?? null) === 1062) {
                    return FinancialMovement::where('payment_id', $payment->id)->firstOrFail();
                }
                throw $e;
            }

            $this->audit->log($movement, AuditLogger::INSERT, ['payment_id' => $payment->id, 'amount' => $movement->amount]);

            return $movement;
        });
    }

    private function storeReceipt(FinancialMovement $movement, UploadedFile $receipt): string
    {
        $extension = strtolower($receipt->extension() ?: $receipt->getClientOriginalExtension());

        return $receipt->storeAs(
            "org/{$this->current->id()}/finanzas/comprobantes",
            "{$movement->id}.{$extension}",
            'local',
        );
    }

    private function lockOpenSession(string $sessionId): CashSession
    {
        $session = CashSession::whereKey($sessionId)->lockForUpdate()->first();

        if (! $session?->isOpen()) {
            throw new BusinessRuleException('La caja seleccionada ya fue cortada; elige una caja abierta.');
        }

        return $session;
    }
}
