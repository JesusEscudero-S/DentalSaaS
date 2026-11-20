<?php

namespace App\Enums;

/** clinical_files.file_type */
enum ClinicalFileType: string
{
    case Radiografia = 'radiografia';
    case FotografiaIntraoral = 'fotografia_intraoral';
    case Consentimiento = 'consentimiento';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Radiografia => 'Radiografía',
            self::FotografiaIntraoral => 'Fotografía intraoral',
            self::Consentimiento => 'Consentimiento',
            self::Otro => 'Otro',
        };
    }

    /** @return array<string, string> [valor => texto] para el select */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
