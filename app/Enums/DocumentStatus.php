<?php

namespace App\Enums;

/** Estado derivado de un documento de plantilla (no se guarda). */
enum DocumentStatus: string
{
    case Generated = 'generado';
    case PendingSignature = 'pendiente';
    case Signed = 'firmado';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generado',
            self::PendingSignature => 'Pendiente de firma',
            self::Signed => 'Firmado',
        };
    }

    /** Color del badge (x-ui.badge). */
    public function color(): string
    {
        return match ($this) {
            self::Generated => 'slate',
            self::PendingSignature => 'amber',
            self::Signed => 'green',
        };
    }
}
