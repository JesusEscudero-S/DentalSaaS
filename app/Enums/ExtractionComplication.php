<?php

namespace App\Enums;

/** Complicaciones en extracciones pasadas (Historia Clínica Dental). */
enum ExtractionComplication: string
{
    case ProlongedBleeding = 'hemorragia_prolongada';
    case HealingProblems = 'problemas_cicatrizacion';

    public function label(): string
    {
        return match ($this) {
            self::ProlongedBleeding => 'Hemorragia prolongada',
            self::HealingProblems => 'Problemas de cicatrización',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
