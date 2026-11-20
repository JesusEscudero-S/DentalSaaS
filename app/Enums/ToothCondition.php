<?php

namespace App\Enums;

/** tooth_findings.condition (los colores siguen la leyenda del odontograma de Figma). */
enum ToothCondition: string
{
    case Sano = 'sano';
    case Caries = 'caries';
    case Endodoncia = 'endodoncia';
    case Restauracion = 'restauracion';
    case Ausente = 'ausente';

    public function label(): string
    {
        return match ($this) {
            self::Sano => 'Sano',
            self::Caries => 'Caries',
            self::Endodoncia => 'Endodoncia',
            self::Restauracion => 'Restauración',
            self::Ausente => 'Ausente',
        };
    }

    /** ¿Lleva caras afectadas? Una pieza sana, ausente o con endodoncia no las lleva. */
    public function usesSurfaces(): bool
    {
        return in_array($this, [self::Caries, self::Restauracion], true);
    }

    /** Clases de la casilla del diente, escritas completas para que Tailwind las encuentre. */
    public function classes(): string
    {
        return match ($this) {
            self::Sano => 'border-slate-200 bg-white text-slate-500',
            self::Caries => 'border-amber-500 bg-amber-50 text-amber-700',
            self::Endodoncia => 'border-primary-600 bg-primary-50 text-primary-600',
            self::Restauracion => 'border-emerald-500 bg-emerald-50 text-emerald-700',
            self::Ausente => 'border-slate-300 bg-slate-100 text-slate-400',
        };
    }

    /** Clases del cuadrito de la leyenda. */
    public function swatch(): string
    {
        return match ($this) {
            self::Sano => 'border-slate-300 bg-white',
            self::Caries => 'border-amber-500 bg-amber-50',
            self::Endodoncia => 'border-primary-600 bg-primary-50',
            self::Restauracion => 'border-emerald-500 bg-emerald-50',
            self::Ausente => 'border-slate-300 bg-slate-100',
        };
    }
}
