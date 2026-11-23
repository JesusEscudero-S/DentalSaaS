<?php

namespace App\Enums;

/** Valores de payments.method y financial_movements.method. Compartido con el módulo 9 (Pagos). */
enum PaymentMethod: string
{
    case Cash = 'efectivo';
    case Card = 'tarjeta';
    case Transfer = 'transferencia';
    case DirectDebit = 'domiciliado';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
            self::DirectDebit => 'Domiciliado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
