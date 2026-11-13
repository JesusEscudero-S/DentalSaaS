<?php

namespace App\Http\Controllers\Patients;

use App\Enums\PatientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Patients\PatientService;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function __construct(private PatientService $patients) {}

    /** RF 3.2: listado y búsqueda. Por defecto solo pacientes activos. */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $patients = $this->filteredQuery($filters)
            ->with(['assignedDoctor.user', 'fiscalProfile'])
            ->withLastVisit()
            ->withBalance()
            ->orderBy('full_name')
            ->paginate(8)
            ->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'filters' => $filters,
            'doctors' => $this->doctors(),
            'registeredCount' => Patient::count(),
        ]);
    }

    /** RF 3.1 */
    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = $this->patients->create($request->validated());

        return redirect()
            ->route('patients.index', ['q' => $patient->folio, 'estado' => 'todos'])
            ->with('toast', ['type' => 'success', 'title' => 'Paciente registrado exitosamente', 'message' => "{$patient->full_name} ({$patient->folio})"]);
    }

    /** Perfil del paciente con sus contactos de emergencia debajo (RF 3.2 y 3.6). */
    public function show(Patient $patient): View
    {
        $patient->load(['guardians', 'fiscalProfile', 'assignedDoctor.user']);

        $balance = Patient::whereKey($patient->id)->withBalance()->first()?->balance ?? 0;

        return view('patients.show', [
            'patient' => $patient,
            'doctors' => $this->doctors(),
            'summary' => $this->summary($patient, $balance),
        ]);
    }

    /** RF 3.3 */
    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->patients->update($patient, $request->validated());

        return back()->with('toast', ['type' => 'success', 'title' => 'Perfil actualizado correctamente', 'message' => $patient->full_name]);
    }

    /** RF 3.4 */
    public function deactivate(Patient $patient): RedirectResponse
    {
        $this->patients->deactivate($patient);

        return back()->with('toast', ['type' => 'success', 'title' => 'Paciente desactivado', 'message' => "{$patient->full_name} ({$patient->folio})"]);
    }

    public function reactivate(Patient $patient): RedirectResponse
    {
        $this->patients->reactivate($patient);

        return back()->with('toast', ['type' => 'success', 'title' => 'Paciente reactivado', 'message' => "{$patient->full_name} ({$patient->folio})"]);
    }

    /** Botón "Exportar" de Figma: CSV del listado con los filtros aplicados. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request))->with('assignedDoctor.user')->orderBy('full_name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
            fputcsv($out, ['Folio', 'Nombre completo', 'Fecha de nacimiento', 'Edad', 'Sexo', 'Teléfono', 'Email', 'Doctor asignado', 'Estado', 'Fecha de alta']);

            $query->chunk(500, function ($patients) use ($out) {
                foreach ($patients as $p) {
                    fputcsv($out, [
                        $p->folio, $p->full_name, $p->birth_date?->format('d/m/Y'), $p->age, $p->sex->label(),
                        Ui::phone($p->phone), $p->email, $p->assignedDoctor?->displayName(), $p->status->label(),
                        $p->created_at?->format('d/m/Y'),
                    ]);
                }
            });

            fclose($out);
        }, 'pacientes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{q: string, estado: string, doctor: string} */
    private function filters(Request $request): array
    {
        $estado = $request->query('estado', 'activos');

        return [
            'q' => trim((string) $request->query('q', '')),
            'estado' => in_array($estado, ['activos', 'inactivos', 'todos'], true) ? $estado : 'activos',
            'doctor' => (string) $request->query('doctor', ''),
        ];
    }

    /** @param  array{q: string, estado: string, doctor: string}  $filters */
    private function filteredQuery(array $filters): Builder
    {
        return Patient::query()
            ->search($filters['q'])
            ->when($filters['estado'] === 'activos', fn ($q) => $q->where('status', PatientStatus::Active->value))
            ->when($filters['estado'] === 'inactivos', fn ($q) => $q->where('status', PatientStatus::Inactive->value))
            ->when($filters['doctor'] !== '', fn ($q) => $q->where('assigned_doctor_member_id', $filters['doctor']));
    }

    /** @return Collection<int, OrganizationMember> */
    private function doctors()
    {
        return OrganizationMember::doctors()
            ->with('user')
            ->get()
            ->sortBy(fn ($m) => $m->displayName())
            ->values();
    }

    /** Tarjetas de resumen del perfil (diseño "Expediente clínico" de Figma). */
    private function summary(Patient $patient, float $balance): array
    {
        $nextAppointment = DB::table('appointments')
            ->leftJoin('treatments', 'treatments.id', '=', 'appointments.treatment_id')
            ->leftJoin('organization_members', 'organization_members.id', '=', 'appointments.doctor_member_id')
            ->leftJoin('users', 'users.id', '=', 'organization_members.user_id')
            ->where('appointments.organization_id', $patient->organization_id)
            ->where('appointments.patient_id', $patient->id)
            ->where('appointments.starts_at', '>=', now())
            ->whereIn('appointments.status', ['pendiente', 'confirmada'])
            ->orderBy('appointments.starts_at')
            ->first(['appointments.starts_at', 'treatments.name as treatment', 'users.full_name as doctor']);

        $lastVisit = DB::table('appointments')
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('status', 'completada')
            ->max('starts_at');

        $activeTreatments = DB::table('patient_treatments')
            ->join('treatments', 'treatments.id', '=', 'patient_treatments.treatment_id')
            ->where('patient_treatments.organization_id', $patient->organization_id)
            ->where('patient_treatments.patient_id', $patient->id)
            ->whereIn('patient_treatments.status', ['planeado', 'en_progreso'])
            ->pluck('treatments.name');

        $pendingInvoices = DB::table('invoices')
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->whereNotIn('fiscal_status', ['borrador', 'cancelada', 'error'])
            ->where('collection_status', '!=', 'pagada')
            ->count();

        return [
            'next_appointment' => $nextAppointment,
            'last_visit' => $lastVisit,
            'balance' => $balance,
            'pending_invoices' => $pendingInvoices,
            'active_treatments' => $activeTreatments,
        ];
    }
}
