<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Regla de negocio que impide la operación. El mensaje (en español) se muestra al usuario como
 * toast de error; no es un fallo del sistema, así que no se registra en el log.
 */
class BusinessRuleException extends RuntimeException
{
    /** Mensajes de los triggers (SQLSTATE 45000) traducidos para el usuario. */
    private const TRIGGER_MESSAGES = [
        'Sesion de caja cerrada: use cash_session_adjustments' => 'La caja ya fue cortada; registra un ajuste de caja.',
        'Sesion de caja cerrada: no se puede eliminar' => 'La caja ya fue cortada; no se puede eliminar.',
    ];

    /** Convierte el error de un trigger en una regla de negocio; null si no es de un trigger. */
    public static function fromQueryException(QueryException $e): ?self
    {
        if (($e->errorInfo[0] ?? null) !== '45000') {
            return null;
        }

        $message = (string) ($e->errorInfo[2] ?? '');

        return new self(self::TRIGGER_MESSAGES[$message] ?? "La base de datos rechazó la operación: {$message}", 0, $e);
    }

    public function report(): void {}

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('toast', ['type' => 'error', 'title' => $this->getMessage()]);
    }
}
