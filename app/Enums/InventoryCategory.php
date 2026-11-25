<?php

namespace App\Enums;

/**
 * Catálogo de categorías de insumos (RF 10.1). En la BD inventory_items.category es texto libre,
 * así que se guarda el texto del catálogo tal cual (el valor del caso).
 */
enum InventoryCategory: string
{
    case Anesthetics = 'Anestésicos';
    case Protection = 'Protección';
    case Restorative = 'Restaurativos';
    case Instruments = 'Instrumental';
    case Consumables = 'Consumibles';
    case Surgery = 'Cirugía';
    case Medication = 'Medicamentos';
    case Other = 'Otros';

    public function label(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
