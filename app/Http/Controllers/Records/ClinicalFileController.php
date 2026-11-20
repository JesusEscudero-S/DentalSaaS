<?php

namespace App\Http\Controllers\Records;

use App\Enums\ClinicalFileType;
use App\Http\Requests\Records\StoreClinicalFileRequest;
use App\Models\ClinicalFile;
use App\Models\Patient;
use App\Services\Records\ClinicalFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pestaña "Archivos" (RF 6.7 Subir / 6.8 Eliminar). */
class ClinicalFileController extends RecordController
{
    public function __construct(private ClinicalFileService $files) {}

    public function index(Patient $patient): View
    {
        return view('records.files', [
            ...$this->shell($patient),
            'files' => $this->files->filesFor($patient),
            'fileTypes' => ClinicalFileType::options(),
        ]);
    }

    public function store(StoreClinicalFileRequest $request, Patient $patient): RedirectResponse
    {
        $saved = $this->files->store(
            $patient,
            $request->file('files'),
            ClinicalFileType::from($request->validated('file_type')),
            $request->validated('description'),
            $this->member(),
        );

        $count = count($saved);

        return back()->with('toast', $this->toast('Archivos cargados', $count === 1 ? $saved[0]->display_name : "{$count} archivos"));   // mensaje del PDF
    }

    /**
     * Ver (?ver=1, en el navegador) o descargar. {clinicalFile} se resuelve con scopeBindings:
     * debe ser un archivo de este paciente (y de esta clínica), si no, 404.
     */
    public function show(Request $request, Patient $patient, ClinicalFile $clinicalFile): StreamedResponse
    {
        return $this->files->response($clinicalFile, inline: $request->boolean('ver'));
    }

    public function destroy(Patient $patient, ClinicalFile $clinicalFile): RedirectResponse
    {
        $this->files->delete($clinicalFile);

        return back()->with('toast', $this->toast('Archivo eliminado', $clinicalFile->display_name));   // mensaje del PDF
    }
}
