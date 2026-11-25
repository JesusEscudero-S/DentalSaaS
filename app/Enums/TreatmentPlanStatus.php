<?php

namespace App\Enums;

/** Módulo 7: estatus del plan de tratamiento y sus cambios válidos. */
enum TreatmentPlanStatus: string
{
    case Proposed = 'propuesto';
    case Authorized = 'autorizado';
    case InProgress = 'en_proceso';
    case Completed = 'concluido';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Propuesto',
            self::Authorized => 'Autorizado',
            self::InProgress => 'En proceso',
            self::Completed => 'Concluido',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Color del badge (componente ui.badge). */
    public function color(): string
    {
        return match ($this) {
            self::Proposed => 'amber',
            self::Authorized => 'blue',
            self::InProgress => 'violet',
            self::Completed => 'green',
            self::Cancelled => 'slate',
        };
    }

    /** Concluido y Cancelado quedan en solo lectura. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** RN-T2: solo se registran sesiones en planes autorizados o en proceso. */
    public function acceptsSessions(): bool
    {
        return in_array($this, [self::Authorized, self::InProgress], true);
    }

    /** Procedimientos y costos solo se editan antes de la primera sesión. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Proposed, self::Authorized], true);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Proposed => in_array($to, [self::Authorized, self::Cancelled], true),
            self::Authorized => in_array($to, [self::Proposed, self::InProgress, self::Completed, self::Cancelled], true),
            self::InProgress => in_array($to, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
