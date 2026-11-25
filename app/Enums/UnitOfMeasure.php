<?php

namespace App\Enums;

/**
 * Unidades de medida de los insumos (inventory_items.unit_of_measure, texto en la BD).
 * Se muestran junto al stock como en Figma: "8 cartucho".
 */
enum UnitOfMeasure: string
{
    case Piece = 'pieza';
    case Box = 'caja';
    case Pack = 'paquete';
    case Cartridge = 'cartucho';
    case Syringe = 'jeringa';
    case Bottle = 'frasco';
    case Bag = 'bolsa';
    case Roll = 'rollo';
    case Tube = 'tubo';
    case Envelope = 'sobre';
    case Pair = 'par';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
