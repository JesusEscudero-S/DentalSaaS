<?php

namespace App\Enums;

/** Valores del comentario de consent_acceptances.method en el esquema. */
enum ConsentMethod: string
{
    case ScannedSignature = 'firma_autografa_escaneada';
    case DigitalSignature = 'firma_digital';
    case Verbal = 'verbal_registrada';

    public function label(): string
    {
        return match ($this) {
            self::ScannedSignature => 'Firma autógrafa',
            self::DigitalSignature => 'Firma digital',
            self::Verbal => 'Verbal registrada',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
