<?php

namespace App\Http\Controllers\Records;

use App\Enums\NoteType;
use App\Http\Requests\Records\StoreAddendumRequest;
use App\Http\Requests\Records\StoreClinicalNoteRequest;
use App\Http\Requests\Records\UpdateClinicalNoteRequest;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\Treatment;
use App\Services\Records\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notas clínicas = diagnóstico dental del PDF (RF 6.4 Registrar / 6.5 Modificar).
 *   - Nota simple: modal "Nueva nota" de Figma (pestaña Notas).
 *   - Nota SOAP: página "Nueva nota clínica" de Figma.
 */
class ClinicalNoteController extends RecordController
{
    public function __construct(private NoteService $notes) {}

    /** Pestaña "Notas". */
    public function index(Patient $patient): View
    {
        return view('records.notes.index', [
            ...$this->shell($patient),
            ...$this->formOptions($patient),
            'notes' => $this->notes->notesFor($patient),
        ]);
    }

    /**
     * Página de nota SOAP. Con ?cita=<uuid> (botón "Abrir expediente" de la Agenda)
     * la nota queda ligada a esa cita y con su doctor.
     */
    public function create(Request $request, Patient $patient): View
    {
        $options = $this->formOptions($patient);
        $appointment = $options['appointments']->firstWhere('id', $request->query('cita'));

        return view('records.notes.form', [
            ...$this->shell($patient),
            ...$options,
            'note' => null,
            'appointment' => $appointment,
            'defaultDoctorId' => $appointment?->doctor_member_id ?? $this->defaultDoctorId($patient),
        ]);
    }

    public function store(StoreClinicalNoteRequest $request, Patient $patient): RedirectResponse
    {
        return $this->attempt(function () use ($request, $patient) {
            $note = $this->notes->create($patient, $request->validated(), $this->member(), $request->wantsToSign(), 'createNote');

            return redirect()
                ->route('records.notes.index', $patient)
                ->with('toast', $this->toast('Diagnóstico guardado', $note->isSigned() ? 'Nota firmada' : 'Guardada como borrador'));   // mensaje del PDF
        });
    }

    /** Ver una nota: si es borrador se muestra el formulario para editarla; si está firmada, en solo lectura. */
    public function show(Patient $patient, ClinicalNote $note): View
    {
        abort_unless($note->belongsToPatient($patient), 404);
        $note->load(['encounter.appointment', 'responsibleDoctor.user', 'author.user', 'signedBy.user', 'treatment', 'addenda.author.user']);

        return view('records.notes.form', [
            ...$this->shell($patient),
            ...$this->formOptions($patient),
            'note' => $note,
            'appointment' => $note->encounter->appointment,
            'defaultDoctorId' => $note->responsible_doctor_member_id,
        ]);
    }

    /** RF 6.5 Modificar diagnóstico (solo borradores). */
    public function update(UpdateClinicalNoteRequest $request, Patient $patient, ClinicalNote $note): RedirectResponse
    {
        abort_unless($note->belongsToPatient($patient), 404);

        return $this->attempt(function () use ($request, $patient, $note) {
            $note = $this->notes->update($note, $request->validated(), $this->member(), $request->wantsToSign(), 'editNote');

            return redirect()
                ->route('records.notes.show', [$patient, $note])
                ->with('toast', $this->toast('Modificación guardada', $note->isSigned() ? 'Nota firmada' : 'Borrador actualizado'));   // mensaje del PDF
        });
    }

    /** Firmar un borrador desde la lista. */
    public function sign(Patient $patient, ClinicalNote $note): RedirectResponse
    {
        abort_unless($note->belongsToPatient($patient), 404);

        return $this->attempt(function () use ($note) {
            $this->notes->sign($note, $this->member());

            return back()->with('toast', $this->toast('Nota firmada', 'Ya no se puede modificar; las correcciones van como adenda.'));
        });
    }

    public function addendum(StoreAddendumRequest $request, Patient $patient, ClinicalNote $note): RedirectResponse
    {
        abort_unless($note->belongsToPatient($patient), 404);

        return $this->attempt(function () use ($request, $note) {
            $addendum = $this->notes->addAddendum($note, $request->validated('content'), $request->validated('reason'), $this->member());

            return back()->with('toast', $this->toast('Adenda agregada', "Versión {$addendum->version_number}"));
        });
    }

    // ─────────────────────────────────────────────────────────────

    /** Opciones de los selects del formulario. */
    private function formOptions(Patient $patient): array
    {
        return [
            // Catálogo del módulo 5. Si está vacío, el select no se muestra.
            'treatments' => Treatment::active()->orderBy('name')->get(['id', 'name']),
            'appointments' => $this->notes->linkableAppointments($patient),
            'noteTypes' => NoteType::cases(),
            'defaultDoctorId' => $this->defaultDoctorId($patient),
        ];
    }

    /** Doctor sugerido: el usuario actual si es doctor; si no, el doctor asignado al paciente. */
    private function defaultDoctorId(Patient $patient): ?string
    {
        $member = $this->member();

        return $member->doctorProfile()->exists() ? $member->id : $patient->assigned_doctor_member_id;
    }
}
