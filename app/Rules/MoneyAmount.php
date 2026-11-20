<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Importe para una columna DECIMAL(14,2): máximo dos decimales y hasta 999,999,999,999.99.
 * Compara con bcmath, nunca con float.
 */
class MoneyAmount implements ValidationRule
{
    public const MAX = '999999999999.99';

    /**
     * @param  bool  $allowZero  acepta 0 (p. ej. monto de apertura)
     * @param  bool  $signed  acepta negativos pero no 0 (p. ej. ajustes de caja)
     */
    public function __construct(
        private bool $allowZero = false,
        private bool $signed = false,
    ) {}

    /** "$1,250.50" → "1250.50"; vacío → null. Para usar en prepareForValidation(). */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $value;
        }

        $clean = preg_replace('/[\s$,]/', '', (string) $value);

        return $clean === '' ? null : $clean;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            $fail('El :attribute debe ser un número.');

            return;
        }

        if (preg_match('/\.\d{3,}$/', $value)) {
            $fail('El :attribute admite máximo dos decimales.');

            return;
        }

        $sign = bccomp($value, '0', 2);

        if ($this->signed && $sign === 0) {
            $fail('El :attribute no puede ser 0.');
        } elseif (! $this->signed && ! $this->allowZero && $sign <= 0) {
            $fail('El :attribute debe ser mayor que 0.');
        } elseif (! $this->signed && $sign < 0) {
            $fail('El :attribute no puede ser negativo.');
        } elseif (bccomp(ltrim($value, '-'), self::MAX, 2) > 0) {
            $fail('El :attribute no puede ser mayor que 999,999,999,999.99.');
        }
    }
}
