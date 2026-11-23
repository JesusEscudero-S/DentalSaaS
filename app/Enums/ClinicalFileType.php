<?php

namespace App\Enums;

/** clinical_files.file_type (lo usan el Expediente y Documentos). Casos en inglés, como pide CLAUDE.md. */
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

    /** @return array<string, string> [valor => texto] para el select */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
