<?php

namespace App\Enums;

/** Caras de una pieza. Se guardan como arreglo JSON en tooth_findings.surfaces: ["O","M"]. */
enum ToothSurface: string
{
    case Vestibular = 'V';
    case Lingual = 'L';
    case Mesial = 'M';
    case Distal = 'D';
    case Oclusal = 'O';

    public function label(): string
    {
        return match ($this) {
            self::Vestibular => 'Vestibular',
            self::Lingual => 'Lingual',
            self::Mesial => 'Mesial',
            self::Distal => 'Distal',
            self::Oclusal => 'Oclusal',
        };
    }

    /** @return array<string, string> [valor => texto] */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $surface) => [$surface->value => $surface->label()])->all();
    }
}
