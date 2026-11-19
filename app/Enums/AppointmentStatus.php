<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case EnCurso = 'en_curso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';
    case NoAsistio = 'no_asistio';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::EnCurso => 'En curso',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
            self::NoAsistio => 'No asistió',
        };
    }

    public static function blocking(): array
    {
        return [self::Pendiente, self::Confirmada, self::EnCurso];
    }
}
