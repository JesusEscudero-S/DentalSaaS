<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentTemplate;
use App\Http\Controllers\Controller;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Historial, plantillas, detalle, descarga y baja de documentos. */
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(Request $request): View
    {
        $estado = $request->query('estado', 'todos');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'plantilla' => DocumentTemplate::tryFrom((string) $request->query('plantilla'))?->value ?? '',
            'estado' => in_array($estado, ['pendiente', 'firmado', 'todos'], true) ? $estado : 'todos',
            'desde' => $this->dateFilter($request->query('desde')),
            'hasta' => $this->dateFilter($request->query('hasta')),
            'paciente' => (string) $request->query('paciente', ''),
        ];
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
        $authorizationKey = 'org/%/documentos/'.DocumentTemplate::TreatmentAuthorization->value.'/%';

        $documents = ClinicalFile::documents()
            ->with(['patient', 'uploadedBy.user', 'consentAcceptance'])
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas('patient', fn ($p) => $p->where(
                fn ($w) => $w->where('full_name', 'like', $like)->orWhere('folio', 'like', $like)
            )))
            ->when($filters['plantilla'] !== '', fn ($q) => $q->where('storage_key', 'like', "org/%/documentos/{$filters['plantilla']}/%"))
            ->when($filters['estado'] === 'pendiente', fn ($q) => $q->where('storage_key', 'like', $authorizationKey)->doesntHave('consentAcceptance'))
            ->when($filters['estado'] === 'firmado', fn ($q) => $q->has('consentAcceptance'))
            ->when($filters['desde'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['desde']))
            ->when($filters['hasta'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['hasta']))
            ->when($filters['paciente'] !== '', fn ($q) => $q->where('patient_id', $filters['paciente']))
            ->orderByDesc('created_at')
            ->paginate(8)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'filters' => $filters,
            'patient' => $filters['paciente'] !== '' ? Patient::find($filters['paciente']) : null,
        ]);
    }

    /** Tarjetas de plantillas. Con ?paciente= el selector ya trae al paciente elegido. */
    public function create(Request $request): View
    {
        $patient = Patient::active()->find((string) $request->query('paciente'));

        return view('documents.create', [
            'templates' => DocumentTemplate::cases(),
            'preselected' => $patient ? $this->patientOption($patient) : null,
        ]);
    }

    /** Buscador del modal "Seleccionar paciente": pacientes activos (Patient::search). */
    public function patients(Request $request): JsonResponse
    {
        $patients = Patient::active()
            ->search((string) $request->query('q', ''))
            ->orderBy('full_name')
            ->limit(8)
            ->get();

        return response()->json($patients->map(fn (Patient $p) => $this->patientOption($p))->values());
    }

    /** PDF de la plantilla en blanco. */
    public function preview(DocumentTemplate $template): Response
    {
        return response($this->documents->renderPdf($template), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-'.$template->value.'.pdf"',
        ]);
    }

    public function show(ClinicalFile $document): View
    {
        $this->ensureDocument($document);
        $document->load(['patient.guardians', 'uploadedBy.user', 'consentAcceptance.witness.user']);

        return view('documents.show', [
            'document' => $document,
            'template' => $document->template(),
            'signedScan' => $document->signedScan(),
            'witnesses' => OrganizationMember::where('is_active', true)->with('user')->get()
                ->sortBy(fn (OrganizationMember $m) => $m->displayName())
                ->values(),
        ]);
    }

    public function download(ClinicalFile $document): StreamedResponse
    {
        $this->ensureDocument($document);
        abort_unless(Storage::disk('local')->exists($document->storage_key), 404);

        return Storage::disk('local')->download($document->storage_key, $document->downloadName(), ['Content-Type' => $document->mime_type]);
    }

    /** PDF "inline" para el visor y para imprimir. */
    public function stream(ClinicalFile $document): StreamedResponse
    {
        $this->ensureDocument($document);
        abort_unless(Storage::disk('local')->exists($document->storage_key), 404);

        return Storage::disk('local')->response($document->storage_key, $document->downloadName(), ['Content-Type' => $document->mime_type]);
    }

    public function destroy(ClinicalFile $document): RedirectResponse
    {
        $this->ensureDocument($document);
        $this->documents->delete($document);

        return redirect()
            ->route('documents.index')
            ->with('toast', ['type' => 'success', 'title' => 'Documento eliminado', 'message' => $document->display_name]);
    }

    /** Solo los PDF de plantilla son "documentos"; los demás archivos clínicos son del módulo 6. */
    private function ensureDocument(ClinicalFile $document): void
    {
        abort_if($document->template() === null, 404);
    }

    /** @return array{id: string, name: string, folio: string, age: ?int, initials: string, color: string} */
    private function patientOption(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->full_name,
            'folio' => $patient->folio,
            'age' => $patient->age,
            'initials' => $patient->initials,
            'color' => $patient->avatar_color,
        ];
    }

    private function dateFilter(mixed $value): string
    {
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
}
