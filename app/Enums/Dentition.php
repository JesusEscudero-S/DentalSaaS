<?php

namespace App\Enums;

/** tooth_findings.dentition y las piezas FDI válidas de cada una (CHECK chk_tf_fdi de la BD). */
enum Dentition: string
{
    case Permanente = 'permanente';
    case Temporal = 'temporal';

    public function label(): string
    {
        return match ($this) {
            self::Permanente => 'Permanente',
            self::Temporal => 'Temporal',
        };
    }

    /**
     * Las dos filas del odontograma vistas de frente al paciente (como en Figma):
     * arriba 18…11 | 21…28, abajo 48…41 | 31…38.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public function rows(): array
    {
        return match ($this) {
            self::Permanente => [
                [...range(18, 11), ...range(21, 28)],
                [...range(48, 41), ...range(31, 38)],
            ],
            self::Temporal => [
                [...range(55, 51), ...range(61, 65)],
                [...range(85, 81), ...range(71, 75)],
            ],
        };
    }

    /** @return array<int, int> */
    public function teeth(): array
    {
        return array_merge(...$this->rows());
    }

    /** ¿A qué dentición pertenece una pieza? null si no es una pieza FDI válida. */
    public static function forTooth(int $tooth): ?self
    {
        foreach (self::cases() as $dentition) {
            if (in_array($tooth, $dentition->teeth(), true)) {
                return $dentition;
            }
        }

        return null;
    }
}
