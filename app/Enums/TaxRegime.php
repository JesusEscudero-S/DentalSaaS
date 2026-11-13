<?php

namespace App\Enums;

/** Catálogo SAT c_RegimenFiscal (patient_fiscal_profiles.tax_regime_code). */
enum TaxRegime: string
{
    case GeneralPM = '601';
    case NonProfitPM = '603';
    case Salaries = '605';
    case Leasing = '606';
    case AssetSales = '607';
    case OtherIncome = '608';
    case Dividends = '611';
    case BusinessAndProfessional = '612';
    case Interest = '614';
    case Prizes = '615';
    case NoTaxObligations = '616';
    case Cooperatives = '620';
    case FiscalIncorporation = '621';
    case Agricultural = '622';
    case Platforms = '625';
    case Resico = '626';

    public function label(): string
    {
        return $this->value.' - '.match ($this) {
            self::GeneralPM => 'General de Ley Personas Morales',
            self::NonProfitPM => 'Personas Morales con Fines no Lucrativos',
            self::Salaries => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            self::Leasing => 'Arrendamiento',
            self::AssetSales => 'Régimen de Enajenación o Adquisición de Bienes',
            self::OtherIncome => 'Demás ingresos',
            self::Dividends => 'Ingresos por Dividendos (socios y accionistas)',
            self::BusinessAndProfessional => 'Personas Físicas con Actividades Empresariales y Profesionales',
            self::Interest => 'Ingresos por intereses',
            self::Prizes => 'Régimen de los ingresos por obtención de premios',
            self::NoTaxObligations => 'Sin obligaciones fiscales',
            self::Cooperatives => 'Sociedades Cooperativas de Producción',
            self::FiscalIncorporation => 'Incorporación Fiscal',
            self::Agricultural => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            self::Platforms => 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            self::Resico => 'Régimen Simplificado de Confianza',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
