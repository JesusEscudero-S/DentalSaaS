<?php

namespace App\Http\Controllers\Records;

use App\Http\Requests\Records\UpdateMedicalHistoryRequest;
use App\Models\Patient;
use App\Services\Records\MedicalRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Pestaña Historial y antecedentes clínicos (RF 6.1 / 6.2). */
class MedicalRecordController extends RecordController
{
    public function __construct(private MedicalRecordService $records) {}

    /** Pestaña "Historial": consultas realizadas y citas programadas (Figma). */
    public function history(Patient $patient): View
    {
        $shell = $this->shell($patient);

        return view('records.history', [
            ...$shell,
            'timeline' => $this->records->timeline($patient, $shell['record']),
        ]);
    }

    /** RF 6.2 Actualizar antecedentes (modal en la pestaña Resumen). */
    public function updateHistory(UpdateMedicalHistoryRequest $request, Patient $patient): RedirectResponse
    {
        $record = $this->shell($patient)['record'];
        $this->records->updateHistory($record, $request->validated());

        return back()->with('toast', $this->toast('Antecedentes actualizados', $patient->full_name));
    }
}
