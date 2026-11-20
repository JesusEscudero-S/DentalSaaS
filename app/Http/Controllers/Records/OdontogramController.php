<?php

namespace App\Http\Controllers\Records;

use App\Enums\Dentition;
use App\Enums\ToothCondition;
use App\Http\Requests\Records\StoreFluorideApplicationRequest;
use App\Http\Requests\Records\StoreToothFindingRequest;
use App\Models\FluorideApplication;
use App\Models\Patient;
use App\Services\Records\OdontogramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pestaña "Odontograma" (RF 6.3) con el control de aplicaciones de flúor (RF 6.6). */
class OdontogramController extends RecordController
{
    public function __construct(private OdontogramService $odontogram) {}

    /** /odontograma?denticion=temporal muestra las piezas de leche (51-85). */
    public function index(Request $request, Patient $patient): View
    {
        $dentition = Dentition::tryFrom((string) $request->query('denticion')) ?? Dentition::Permanente;

        return view('records.odontogram', [
            ...$this->shell($patient),
            'dentition' => $dentition,
            'findings' => $this->odontogram->current($patient),
            'history' => $this->odontogram->history($patient),
            'conditions' => ToothCondition::cases(),
            'fluorideByYear' => $this->odontogram->fluorideByYear($patient),
        ]);
    }

    public function store(StoreToothFindingRequest $request, Patient $patient): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient) {
            $finding = $this->odontogram->record(
                $patient,
                (int) $request->validated('tooth_fdi'),
                ToothCondition::from($request->validated('condition')),
                $request->validated('surfaces') ?? [],
                $this->member(),
            );

            return back()->with('toast', $this->toast('Odontograma actualizado', "Pieza {$finding->tooth_fdi}: {$finding->description()}"));
        });
    }

    public function storeFluoride(StoreFluorideApplicationRequest $request, Patient $patient): RedirectResponse
    {
        $application = $this->odontogram->addFluoride($patient, $request->validated(), $this->member());

        return back()->with('toast', $this->toast('Aplicación de flúor registrada', 'Año de control '.$application->applied_on->year));
    }

    public function destroyFluoride(Patient $patient, FluorideApplication $fluorideApplication): RedirectResponse
    {
        $this->odontogram->removeFluoride($fluorideApplication);

        return back()->with('toast', $this->toast('Aplicación de flúor eliminada'));
    }
}
