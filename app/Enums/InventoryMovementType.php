<?php

namespace App\Enums;

/**
 * Tipo de movimiento de inventario (inventory_movements.movement_type).
 * La cantidad se guarda con signo: positiva suma stock, negativa lo resta.
 */
enum InventoryMovementType: string
{
    case InitialStock = 'inventario_inicial';
    case Entry = 'entrada';
    case Exit = 'salida';
    case Adjustment = 'ajuste';
    case Reversal = 'reversion';

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Inventario inicial',
            self::Entry => 'Entrada',
            self::Exit => 'Salida',
            self::Adjustment => 'Ajuste',
            self::Reversal => 'Reversión',
        };
    }

    /** Color del badge (x-ui.badge). */
    public function color(): string
    {
        return match ($this) {
            self::InitialStock => 'blue',
            self::Entry => 'green',
            self::Exit => 'amber',
            self::Adjustment => 'violet',
            self::Reversal => 'slate',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
