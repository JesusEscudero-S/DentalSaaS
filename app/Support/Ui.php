<?php

namespace App\Support;

/** Ayudas de presentación compartidas por las vistas. */
class Ui
{
    /** Colores de Avatar/Badge definidos en Figma. */
    public const COLORS = ['blue', 'sky', 'green', 'violet', 'amber', 'slate'];

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 2 || count($words) <= 2));

        $first = $words[0] ?? '';
        $second = $words[1] ?? '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($second, 0, 1));
    }

    /** Color estable a partir de un identificador. */
    public static function colorFor(?string $seed): string
    {
        return self::COLORS[crc32((string) $seed) % (count(self::COLORS) - 1)];
    }

    /** 5518472093 -> "55 1847 2093" (formato de Figma). */
    public static function phone(?string $phone): string
    {
        if (! $phone || strlen($phone) !== 10) {
            return (string) $phone;
        }

        return substr($phone, 0, 2).' '.substr($phone, 2, 4).' '.substr($phone, 6);
    }

    public static function money(float|string|null $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    /** "28 may 2026" */
    public static function date($date): string
    {
        if (! $date) {
            return '—';
        }

        $date = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date);
        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        return $date->format('j').' '.$months[(int) $date->format('n') - 1].' '.$date->format('Y');
    }
}
