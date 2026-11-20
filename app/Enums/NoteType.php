<?php

namespace App\Enums;

/** clinical_notes.note_type */
enum NoteType: string
{
    case Simple = 'simple';
    case Soap = 'soap';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Nota simple',
            self::Soap => 'Nota SOAP',
        };
    }
}
