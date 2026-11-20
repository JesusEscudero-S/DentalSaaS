<?php

namespace App\Enums;

/**
 * clinical_notes.status. Una nota firmada es inmutable: el trigger clinical_notes_signed_bu
 * de la BD rechaza cualquier UPDATE (SQLSTATE 45000). Para corregirla se agrega una adenda.
 */
enum NoteStatus: string
{
    case Borrador = 'borrador';
    case Firmada = 'firmada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Firmada => 'Firmada',
        };
    }
}
