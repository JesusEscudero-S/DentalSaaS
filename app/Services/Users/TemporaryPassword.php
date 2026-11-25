<?php

namespace App\Services\Users;

/**
 * Contraseña temporal que cumple la política (8+ caracteres con mayúscula, minúscula y número).
 * Sin caracteres que se confunden al dictarla (0/O, 1/l/I).
 */
class TemporaryPassword
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    public static function generate(int $length = 10): string
    {
        $all = self::UPPER.self::LOWER.self::DIGITS;
        $chars = [self::pick(self::UPPER), self::pick(self::LOWER), self::pick(self::DIGITS)];

        while (count($chars) < $length) {
            $chars[] = self::pick($all);
        }

        // Mezcla con random_int para no depender de un generador predecible.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $pool): string
    {
        return $pool[random_int(0, strlen($pool) - 1)];
    }
}
