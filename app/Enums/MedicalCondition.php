<?php

namespace App\Enums;

/** Padecimientos de la Historia Clínica Dental. Viven solo en las respuestas del documento. */
enum MedicalCondition: string
{
    case Anemia = 'anemia';
    case Arthritis = 'artritis';
    case Hypertension = 'hipertension';
    case Hypotension = 'hipotension';
    case Diabetes = 'diabetes';
    case HeartDisease = 'cardiopatia';
    case Seizures = 'convulsiones';
    case AllergicReactions = 'reacciones_alergicas';
    case Hepatitis = 'hepatitis';
    case Pregnancy = 'embarazo';

    public function label(): string
    {
        return match ($this) {
            self::Anemia => 'Anemia',
            self::Arthritis => 'Artritis',
            self::Hypertension => 'Hipertensión',
            self::Hypotension => 'Hipotensión',
            self::Diabetes => 'Diabetes',
            self::HeartDisease => 'Cardiopatía',
            self::Seizures => 'Convulsiones',
            self::AllergicReactions => 'Reacciones alérgicas',
            self::Hepatitis => 'Hepatitis',
            self::Pregnancy => 'Embarazada',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
