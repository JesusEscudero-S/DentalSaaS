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

    /** Texto que ve el usuario. */
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

    /** Estados que ocupan horario en la agenda (para la regla de empalmes). */
    public static function blocking(): array
    {
        return [self::Pendiente, self::Confirmada, self::EnCurso];
    }

    /** Valores en texto de blocking(), para usarlos en consultas whereIn(). */
    public static function blockingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::blocking());
    }

    /** ¿Esta cita todavía ocupa su horario? */
    public function isBlocking(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    /**
     * Máquina de estados: a qué estados se puede pasar desde el actual.
     *
     *   pendiente ──Confirmar──► confirmada
     *
     *   pendiente / confirmada ──Iniciar──► en_curso ──Finalizar──► completada
     *          │                               │
     *          ├──Cancelar──► cancelada ◄──────┘
     *          └──No asistió──► no_asistio
     *
     * completada, cancelada y no_asistio son finales: ya no cambian.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Pendiente => [self::Confirmada, self::EnCurso, self::Cancelada, self::NoAsistio],
            self::Confirmada => [self::EnCurso, self::Cancelada, self::NoAsistio],
            self::EnCurso => [self::Completada, self::Cancelada],
            default => [],
        }, true);
    }

    /** Solo se reagenda una cita que aún no empieza. */
    public function canReschedule(): bool
    {
        return in_array($this, [self::Pendiente, self::Confirmada], true);
    }
}
