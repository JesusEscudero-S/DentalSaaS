<?php

namespace App\Enums;

enum ClinicalFileType: string
{
    case XRay = 'radiografia';
    case Consent = 'consentimiento';
    case IntraoralPhoto = 'fotografia_intraoral';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::XRay => 'Radiografía',
            self::Consent => 'Consentimiento',
            self::IntraoralPhoto => 'Fotografía intraoral',
            self::Other => 'Otro',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
