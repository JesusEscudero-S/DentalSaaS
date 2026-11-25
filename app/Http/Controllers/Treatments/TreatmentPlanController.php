<?php

namespace App\Http\Controllers\Treatments;

use App\Enums\ConsentMethod;
use App\Http\Controllers\Records\RecordController;
use App\Http\Requests\Treatments\AuthorizeTreatmentPlanRequest;
use App\Http\Requests\Treatments\CancelTreatmentPlanRequest;
use App\Http\Requests\Treatments\SessionNoteRequest;
use App\Http\Requests\Treatments\TreatmentPlanRequest;
use App\Http\Requests\Treatments\TreatmentSessionRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Services\Treatments\TreatmentPlanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Módulo 7. Gestión de tratamientos: pestaña "Tratamientos" del expediente del paciente.
 */
class TreatmentPlanController extends RecordController
{
    public function __construct(private TreatmentPlanService $plans) {}

    public function index(Patient $patient): View
    {
        $plans = $patient->treatmentPlans()
            ->with(['quote', 'doctor.user'])
            ->orderByDesc('diagnosed_on')
            ->orderByDesc('created_at')
            ->get();

        return view('treatments.index', [...$this->shell($patient), 'plans' => $plans]);
    }

    public function create(Patient $patient): View
    {
        return view('treatments.form', [...$this->shell($patient), ...$this->formData(), 'plan' => null, 'initial' => null]);
    }

    public function store(TreatmentPlanRequest $request, Patient $patient): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient) {
            $plan = $this->plans->create($patient, $request->validated());

            return redirect()->route('records.treatments.show', [$patient, $plan])
                ->with('toast', $this->toast('Tratamiento registrado', "{$plan->quote->folio} · Propuesto. Falta la autorización del paciente."));
        });
    }

    public function show(Patient $patient, TreatmentPlan $treatmentPlan): View
    {
        $treatmentPlan->load(['quote.items', 'doctor.user', 'consent', 'versions.quote', 'versions.author', 'sessions.doctor.user', 'sessions.procedures.quoteItem', 'sessions.notes.author']);
        $procedures = $treatmentPlan->procedures();

        return view('treatments.show', [
            ...$this->shell($patient),
            'plan' => $treatmentPlan,
            'procedures' => $procedures,
            'realCost' => $treatmentPlan->realCost($procedures),
            'consentMethods' => ConsentMethod::options(),
        ]);
    }

    public function edit(Patient $patient, TreatmentPlan $treatmentPlan): View|RedirectResponse
    {
        if (! $treatmentPlan->status->isEditable()) {
            return $this->error('Este tratamiento ya tiene sesiones o está cerrado: no se puede modificar.');
        }

        $treatmentPlan->load('quote.items');

        return view('treatments.form', [
            ...$this->shell($patient),
            ...$this->formData(),
            'plan' => $treatmentPlan,
            'initial' => [
                'doctor_member_id' => $treatmentPlan->doctor_member_id,
                'diagnosis' => $treatmentPlan->diagnosis,
                'diagnosed_on' => $treatmentPlan->diagnosed_on->toDateString(),
                'procedures' => $treatmentPlan->quote->items->map(fn ($i) => [
                    'treatment_id' => $i->treatment_id,
                    'tooth_fdi' => $i->tooth_fdi,
                    'unit_price' => number_format((float) $i->unit_price, 2, '.', ''),
                    'notes' => str_contains($i->description_snapshot, ' — ') ? explode(' — ', $i->description_snapshot, 2)[1] : '',
                ])->all(),
            ],
        ]);
    }

    public function update(TreatmentPlanRequest $request, Patient $patient, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient, $treatmentPlan) {
            $wasAuthorized = $treatmentPlan->status->value === 'autorizado';
            $plan = $this->plans->update($treatmentPlan, $request->validated());

            $message = $wasAuthorized && $plan->requires_reauthorization
                ? "Versión {$plan->version}: requiere nueva autorización del paciente. Se avisó al jefe de odontología."
                : $plan->quote->folio;

            return redirect()->route('records.treatments.show', [$patient, $plan])
                ->with('toast', $this->toast('Tratamiento actualizado', $message));
        });
    }

    public function authorizePlan(AuthorizeTreatmentPlanRequest $request, Patient $patient, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        return $this->attempt(function () use ($request, $treatmentPlan) {
            $this->plans->authorize($treatmentPlan, $request->validated('accepted_by_name'), ConsentMethod::from($request->validated('method')));

            return back()->with('toast', $this->toast('Tratamiento autorizado', 'Ya puedes programar y registrar sesiones.'));
        });
    }

    public function storeSession(TreatmentSessionRequest $request, Patient $patient, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        return $this->attempt(function () use ($request, $treatmentPlan) {
            $this->plans->recordSession($treatmentPlan, $request->validated());
            $plan = $treatmentPlan->fresh();

            $toast = $plan->status->value === 'concluido'
                ? $this->toast('Tratamiento concluido', 'Se realizaron todos los procedimientos. El costo real ya está disponible para Facturación y pagos.')
                : $this->toast('Sesión registrada', 'Programa la siguiente sesión en Agenda y citas.');

            return back()->with('toast', $toast);
        });
    }

    public function storeSessionNote(SessionNoteRequest $request, Patient $patient, TreatmentPlan $treatmentPlan, TreatmentSession $session): RedirectResponse
    {
        // La ruta va sin scopeBindings (la sesión no es relación del paciente): se comprueba aquí.
        abort_unless($treatmentPlan->patient_id === $patient->id && $session->plan_id === $treatmentPlan->id, 404);

        return $this->attempt(function () use ($request, $session) {
            $this->plans->addSessionNote($session, $request->validated('note'));

            return back()->with('toast', $this->toast('Corrección agregada'));
        });
    }

    public function cancel(CancelTreatmentPlanRequest $request, Patient $patient, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        return $this->attempt(function () use ($request, $treatmentPlan) {
            $appointments = $this->plans->cancel($treatmentPlan, $request->validated('reason'));

            $message = 'Se avisó al jefe de odontología.'
                .($appointments ? " El paciente tiene {$appointments} ".($appointments === 1 ? 'cita futura' : 'citas futuras').' de este tratamiento: revísalas en Agenda.' : '');

            return back()->with('toast', $this->toast('Tratamiento cancelado', $message));
        });
    }

    /** RN-T7: plan, autorización y resumen en PDF. */
    public function pdf(Patient $patient, TreatmentPlan $treatmentPlan): Response
    {
        $treatmentPlan->load(['quote.items', 'doctor.user', 'consent', 'sessions.doctor.user', 'sessions.procedures.quoteItem', 'sessions.notes']);
        $procedures = $treatmentPlan->procedures();

        return Pdf::loadView('treatments.pdf', [
            'organization' => Organization::find($patient->organization_id),
            'patient' => $patient,
            'plan' => $treatmentPlan,
            'procedures' => $procedures,
            'realCost' => $treatmentPlan->realCost($procedures),
        ])->setPaper('letter')->download("tratamiento-{$treatmentPlan->quote->folio}.pdf");
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
