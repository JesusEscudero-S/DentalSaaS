<?php

namespace App\Enums;

/**
 * Catálogo de parentesco del RF 3.5. La columna en BD es varchar(60);
 * se guarda el código y se restringe en la aplicación.
 */
enum GuardianRelationship: string
{
    case Father = 'padre';
    case Mother = 'madre';
    case Spouse = 'conyuge';
    case Child = 'hijo';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Padre',
            self::Mother => 'Madre',
            self::Spouse => 'Cónyuge',
            self::Child => 'Hijo',
            self::Other => 'Otro',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
