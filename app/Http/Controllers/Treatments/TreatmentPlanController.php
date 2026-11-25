<?php

namespace App\Http\Controllers\Treatments;

use App\Enums\ConsentMethod;
use App\Enums\TreatmentPlanStatus;
use App\Http\Controllers\Records\RecordController;
use App\Http\Requests\Treatments\AuthorizeTreatmentPlanRequest;
use App\Http\Requests\Treatments\CancelTreatmentPlanRequest;
use App\Http\Requests\Treatments\TreatmentPlanRequest;
use App\Http\Requests\Treatments\TreatmentSessionRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\Treatment;
use App\Services\Treatments\TreatmentPlanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Módulo 7. Gestión de tratamientos: pestaña "Tratamientos" del expediente del paciente.
 * Cada plan es un presupuesto ({quote}); ver TreatmentPlanService.
 */
class TreatmentPlanController extends RecordController
{
    public function __construct(private TreatmentPlanService $plans) {}

    public function index(Patient $patient): View
    {
        return view('treatments.index', [...$this->shell($patient), 'plans' => $this->plans->plansFor($patient)]);
    }

    public function create(Patient $patient): View
    {
        return view('treatments.form', [...$this->shell($patient), ...$this->formData(), 'plan' => null, 'initial' => null]);
    }

    public function store(TreatmentPlanRequest $request, Patient $patient): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient) {
            $quote = $this->plans->create($patient, $request->validated());

            return redirect()->route('records.treatments.show', [$patient, $quote])
                ->with('toast', $this->toast('Tratamiento registrado', "{$quote->folio} · Propuesto. Falta la autorización del paciente."));
        });
    }

    public function show(Patient $patient, Quote $quote): View
    {
        return view('treatments.show', [
            ...$this->shell($patient),
            'plan' => $this->plans->plan($quote),
            'consentMethods' => ConsentMethod::options(),
        ]);
    }

    public function edit(Patient $patient, Quote $quote): View|RedirectResponse
    {
        $plan = $this->plans->plan($quote, withSessions: false);

        if (! $plan->status->isEditable()) {
            return $this->error('Este tratamiento ya tiene sesiones o está cerrado: no se puede modificar.');
        }

        return view('treatments.form', [
            ...$this->shell($patient),
            ...$this->formData(),
            'plan' => $plan,
            'initial' => [
                'doctor_member_id' => $quote->doctor_member_id,
                'diagnosis' => $plan->diagnosis,
                'diagnosed_on' => $quote->quote_date->toDateString(),
                'procedures' => $quote->items->map(fn ($i) => [
                    'treatment_id' => $i->treatment_id,
                    'tooth_fdi' => $i->tooth_fdi,
                    'unit_price' => number_format((float) $i->unit_price, 2, '.', ''),
                    'notes' => str_contains($i->description_snapshot, ' — ') ? explode(' — ', $i->description_snapshot, 2)[1] : '',
                ])->all(),
            ],
        ]);
    }

    public function update(TreatmentPlanRequest $request, Patient $patient, Quote $quote): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient, $quote) {
            $wasAuthorized = $this->plans->plan($quote, withSessions: false)->status === TreatmentPlanStatus::Authorized;
            $current = $this->plans->update($quote, $request->validated());

            $message = match (true) {
                $current->is($quote) => $quote->folio,
                $wasAuthorized => "Se creó {$current->folio}: requiere nueva autorización del paciente. Se avisó al jefe de odontología.",
                default => "Se creó {$current->folio} y {$quote->folio} quedó reemplazado.",
            };

            return redirect()->route('records.treatments.show', [$patient, $current])
                ->with('toast', $this->toast('Tratamiento actualizado', $message));
        });
    }

    public function authorizePlan(AuthorizeTreatmentPlanRequest $request, Patient $patient, Quote $quote): RedirectResponse
    {
        return $this->attempt(function () use ($request, $quote) {
            $this->plans->authorize($quote, $request->validated('accepted_by_name'), ConsentMethod::from($request->validated('method')));

            return back()->with('toast', $this->toast('Tratamiento autorizado', 'Ya puedes programar y registrar sesiones.'));
        });
    }

    public function storeSession(TreatmentSessionRequest $request, Patient $patient, Quote $quote): RedirectResponse
    {
        return $this->attempt(function () use ($request, $quote) {
            $this->plans->recordSession($quote, $request->validated());
            $status = $this->plans->plan($quote->fresh(), withSessions: false)->status;

            $toast = $status === TreatmentPlanStatus::Completed
                ? $this->toast('Tratamiento concluido', 'Se realizaron todos los procedimientos. El costo real ya está disponible para Facturación y pagos.')
                : $this->toast('Sesión registrada', 'Quedó como nota en borrador en la pestaña Notas. Programa la siguiente sesión en Agenda y citas.');

            return back()->with('toast', $toast);
        });
    }

    public function cancel(CancelTreatmentPlanRequest $request, Patient $patient, Quote $quote): RedirectResponse
    {
        return $this->attempt(function () use ($request, $quote) {
            $appointments = $this->plans->cancel($quote, $request->validated('reason'));

            $message = 'Se avisó al jefe de odontología.'
                .($appointments ? " El paciente tiene {$appointments} ".($appointments === 1 ? 'cita futura' : 'citas futuras').' de este tratamiento: revísalas en Agenda.' : '');

            return back()->with('toast', $this->toast('Tratamiento cancelado', $message));
        });
    }

    /** RN-T7: plan, autorización y resumen en PDF. */
    public function pdf(Patient $patient, Quote $quote): Response
    {
        return Pdf::loadView('treatments.pdf', [
            'organization' => Organization::find($patient->organization_id),
            'patient' => $patient,
            'plan' => $this->plans->plan($quote),
        ])->setPaper('letter')->download("tratamiento-{$quote->folio}.pdf");
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'catalog' => Treatment::active()->orderBy('name')->get(['id', 'name', 'price']),
            'planDoctors' => TreatmentPlanService::doctors(),
        ];
    }
}
