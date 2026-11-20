<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreConsentRequest;
use App\Models\ClinicalFile;
use App\Services\Documents\ConsentService;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Firma de la Autorización de tratamiento y su escaneo. */
class ConsentController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    public function store(StoreConsentRequest $request, ClinicalFile $document): RedirectResponse
    {
        abort_if($document->template() === null, 404);

        $this->consents->register($document, $request->safe()->except('signed_file'), $request->file('signed_file'));

        return redirect()
            ->route('documents.show', $document)
            ->with('toast', ['type' => 'success', 'title' => 'Firma registrada correctamente', 'message' => $document->display_name]);
    }

    /** Escaneo del documento firmado (inline). */
    public function scan(ClinicalFile $document): StreamedResponse
    {
        abort_if($document->template() === null, 404);
        $scan = $document->signedScan();
        abort_unless($scan && Storage::disk(DocumentService::DISK)->exists($scan->storage_key), 404);

        $name = 'autorizacion-firmada-'.$document->patient?->folio.'.'.pathinfo($scan->storage_key, PATHINFO_EXTENSION);

        return Storage::disk(DocumentService::DISK)->response($scan->storage_key, $name, ['Content-Type' => $scan->mime_type]);
    }
}
