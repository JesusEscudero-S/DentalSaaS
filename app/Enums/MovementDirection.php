<?php

namespace App\Enums;

enum MovementDirection: string
{
    case Income = 'ingreso';
    case Expense = 'egreso';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Ingreso',
            self::Expense => 'Egreso',
        };
    }

    /** Color del badge (x-ui.badge). */
    public function color(): string
    {
        return match ($this) {
            self::Income => 'green',
            self::Expense => 'red',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
