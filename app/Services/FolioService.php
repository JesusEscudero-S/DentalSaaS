<?php

namespace App\Services;

use App\Models\FolioSequence;
use Illuminate\Support\Facades\DB;

/**
 * Genera folios consecutivos por organización (PAC-0001, RX-0001, ...) usando
 * folio_sequences con bloqueo de fila. Debe llamarse dentro de una transacción.
 */
class FolioService
{
    public function next(string $documentType, string $prefix, string $series = '', int $padding = 4): string
    {
        return DB::transaction(function () use ($documentType, $prefix, $series, $padding) {
            $sequence = FolioSequence::query()
                ->where('document_type', $documentType)
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = FolioSequence::create([
                    'document_type' => $documentType,
                    'series' => $series,
                    'prefix' => $prefix,
                    'last_value' => 0,
                ]);
                $sequence = FolioSequence::whereKey($sequence->getKey())->lockForUpdate()->first();
            }

            $sequence->last_value++;
            $sequence->save();

            return $sequence->prefix.str_pad((string) $sequence->last_value, $padding, '0', STR_PAD_LEFT);
        });
    }
}
