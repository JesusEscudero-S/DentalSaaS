<?php

namespace App\Enums;

enum PatientSex: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::Other => 'Otro',
            self::Unspecified => 'Sin especificar',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
