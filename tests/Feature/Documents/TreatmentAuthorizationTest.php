<?php

namespace Tests\Feature\Documents;

use App\Enums\ClinicalFileType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTemplate;
use App\Models\Branch;
use App\Models\ClinicalFile;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\ToothFinding;
use App\Models\Treatment;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TreatmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMember $doctor;

    private Patient $patient;

    private Quote $approved;

    private Quote $draft;

    /**
     * Expediente del paciente: resina realizada en 16, limpieza en progreso, ortodoncia planeada (no se
     * prellena), caries en 16 y ausencia en 36 (un hallazgo reemplazado en 11 no cuenta), un presupuesto
     * aprobado y uno en borrador.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->doctor = $this->member(doctor: true);
        $this->patient = $this->createPatient($this->doctor, ['full_name' => 'MARIO PEREZ', 'assigned_doctor_member_id' => $this->doctor->id]);

        app(CurrentOrganization::class)->set($this->doctor);
        $branch = Branch::create(['name' => 'Matriz', 'is_main' => true]);
        $treatment = fn (string $name) => Treatment::create(['name' => $name, 'duration_minutes' => 30, 'price' => '800']);
        $base = ['patient_id' => $this->patient->id];

        PatientTreatment::create([...$base, 'treatment_id' => $treatment('Resina')->id, 'tooth_fdi' => 16, 'status' => 'realizado', 'completed_at' => now()->subDays(10)]);
        PatientTreatment::create([...$base, 'treatment_id' => $treatment('Limpieza')->id, 'status' => 'en_progreso', 'started_at' => now()->subDays(3)]);
        PatientTreatment::create([...$base, 'treatment_id' => $treatment('Ortodoncia')->id, 'status' => 'planeado']);

        $finding = ['patient_id' => $this->patient->id, 'dentition' => 'permanente', 'author_member_id' => $this->doctor->id];
        ToothFinding::create([...$finding, 'tooth_fdi' => 16, 'condition' => 'caries']);
        ToothFinding::create([...$finding, 'tooth_fdi' => 36, 'condition' => 'ausente']);
        ToothFinding::create([...$finding, 'tooth_fdi' => 11, 'condition' => 'caries', 'superseded_at' => now()]);

        $quote = fn (string $folio, string $status) => Quote::create([
            ...$base, 'folio' => $folio, 'doctor_member_id' => $this->doctor->id, 'branch_id' => $branch->id,
            'quote_date' => today()->subDays(5), 'status' => $status, 'subtotal' => '800.00', 'total' => '800.00',
        ]);
        $this->approved = $quote('PRE-0001', 'aprobado');
        $this->draft = $quote('PRE-0002', 'borrador');
        QuoteItem::create([
            'quote_id' => $this->approved->id, 'line_number' => 1, 'description_snapshot' => 'Resina compuesta', 'tooth_fdi' => 16,
            'quantity' => '1', 'unit_price' => '800', 'line_subtotal' => '800', 'line_discount' => '0', 'line_tax' => '0', 'line_total' => '800',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'patient_id' => $this->patient->id,
            'doctor_member_id' => $this->doctor->id,
            'fluoride' => [['year' => '2024', 'details' => 'Barniz de flúor'], ['year' => '', 'details' => '']],
            'diagnoses' => [['date' => today()->toDateString(), 'text' => 'Caries oclusal en 16']],
            'activities' => [['date' => today()->toDateString(), 'description' => 'Resina en 16', 'doctor_member_id' => $this->doctor->id]],
            'quote_id' => $this->approved->id,
            ...$overrides,
        ];
    }

    private function storedJson(ClinicalFile $document): array
    {
        return json_decode(Storage::disk('local')->get($document->documentDirectory().'/respuestas.json'), true);
    }

    public function test_form_prefills_odontogram_treatments_and_quote(): void
    {
        $this->actingAsMember($this->doctor)
            ->get('/documentos/autorizacion-tratamiento/crear?paciente='.$this->patient->id)
            ->assertOk()
            ->assertSee('MARIO PEREZ')
            ->assertSee('PRE-0001')
            ->assertDontSee('PRE-0002')
            ->assertSee('Diente 16: Caries')
            ->assertSee(DocumentTemplate::CONSENT_DECLARATION)
            ->assertViewHas('findings', fn ($findings) => $findings->all() === [16 => 'caries', 36 => 'ausente'])
            ->assertViewHas('form', fn ($form) => $form['quote_id'] === $this->approved->id
                && array_column($form['activities'], 'description') === ['Resina · diente 16', 'Limpieza (en progreso)']
                && $form['activities'][0]['doctor_member_id'] === $this->doctor->id);

        $empty = $this->createPatient($this->doctor);
        $this->actingAsMember($this->doctor)
            ->get('/documentos/autorizacion-tratamiento/crear?paciente='.$empty->id)
            ->assertOk()
            ->assertSee('Sin hallazgos registrados en el expediente')
            ->assertSee('El paciente no tiene presupuestos enviados o aprobados.');
    }

    public function test_generates_an_authorization_pending_signature_without_touching_the_record(): void
    {
        $response = $this->actingAsMember($this->doctor)->post('/documentos/autorizacion-tratamiento', $this->payload());

        $document = ClinicalFile::documents()->sole();
        $response->assertRedirect(route('documents.show', $document))->assertSessionHas('toast.title', 'Documento generado correctamente');

        $this->assertSame(DocumentTemplate::TreatmentAuthorization, $document->template());
        $this->assertSame(ClinicalFileType::Consent, $document->file_type);
        $this->assertSame(DocumentStatus::PendingSignature, $document->documentStatus());
        $this->assertSame(hash('sha256', Storage::disk('local')->get($document->storage_key)), $document->sha256);

        $answers = $this->storedJson($document)['answers'];
        $this->assertSame([['year' => 2024, 'details' => 'Barniz de flúor']], $answers['fluoride']);
        $this->assertSame([['date' => today()->toDateString(), 'text' => 'Caries oclusal en 16']], $answers['diagnoses']);
        $this->assertSame('Resina en 16', $answers['activities'][0]['description']);
        $this->assertSame($this->doctor->displayName(), $answers['activities'][0]['doctor_name']);
        $this->assertSame($this->approved->id, $answers['quote_id']);

        $record = MedicalRecord::where('patient_id', $this->patient->id)->sole();
        $this->assertNull($record->allergies);
        $this->assertNull($record->medical_history);

        $this->actingAsMember($this->doctor)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Pendiente de firma')
            ->assertSee('Registrar firma');
    }

    public function test_validations_of_dynamic_rows_and_quote(): void
    {
        $nonDoctor = $this->member($this->doctor->organization);
        $otherPatient = $this->createPatient($this->doctor);
        app(CurrentOrganization::class)->set($this->doctor);
        $foreignQuote = $this->approved->replicate()->fill(['folio' => 'PRE-0003', 'patient_id' => $otherPatient->id]);
        $foreignQuote->save();

        $cases = [
            [['fluoride' => [['year' => '1989', 'details' => 'X']]], 'fluoride.0.year', 'El año debe estar entre 1990 y '.now()->year.'.'],
            [['fluoride' => [['year' => '', 'details' => 'Sin año']]], 'fluoride.0.year', 'Indica el año de la aplicación.'],
            [['diagnoses' => [['date' => today()->toDateString(), 'text' => '']]], 'diagnoses.0.text', 'Escribe el diagnóstico.'],
            [['diagnoses' => [['date' => today()->addDay()->toDateString(), 'text' => 'Futuro']]], 'diagnoses.0.date', 'La fecha no puede ser posterior a hoy.'],
            [['activities' => [['date' => '', 'description' => 'X', 'doctor_member_id' => $nonDoctor->id]]], 'activities.0.doctor_member_id', 'El doctor seleccionado no es válido.'],
            [['activities' => [['date' => today()->toDateString(), 'description' => '', 'doctor_member_id' => $this->doctor->id]]], 'activities.0.description', 'Describe la actividad realizada.'],
            [['quote_id' => $this->draft->id], 'quote_id', 'El presupuesto seleccionado no es válido para este paciente.'],
            [['quote_id' => $foreignQuote->id], 'quote_id', 'El presupuesto seleccionado no es válido para este paciente.'],
        ];

        foreach ($cases as [$overrides, $field, $message]) {
            $this->actingAsMember($this->doctor)
                ->post('/documentos/autorizacion-tratamiento', $this->payload($overrides))
                ->assertSessionHasErrors([$field => $message], null, 'treatmentAuthorization');
        }

        $this->assertSame(0, ClinicalFile::count());
    }

    public function test_preview_renders_the_pdf_without_saving_anything(): void
    {
        $response = $this->actingAsMember($this->doctor)
            ->post('/documentos/autorizacion-tratamiento/vista-previa', $this->payload())
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(0, ClinicalFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_new_version_is_prefilled_from_the_previous_answers(): void
    {
        $this->actingAsMember($this->doctor)->post('/documentos/autorizacion-tratamiento', $this->payload());
        $first = ClinicalFile::sole();

        $this->actingAsMember($this->doctor)
            ->get(route('documents.authorization.create', ['paciente' => $this->patient->id, 'desde' => $first->id]))
            ->assertOk()
            ->assertSee('Nueva versión')
            ->assertViewHas('form', fn ($form) => $form['fluoride'] === [['year' => 2024, 'details' => 'Barniz de flúor']]
                && $form['activities'][0]['description'] === 'Resina en 16'
                && $form['diagnoses'][0]['text'] === 'Caries oclusal en 16');

        $this->actingAsMember($this->doctor)
            ->post('/documentos/autorizacion-tratamiento', $this->payload(['previous_document_id' => $first->id, 'quote_id' => '']))
            ->assertSessionHasNoErrors();
        $second = ClinicalFile::whereKeyNot($first->id)->sole();
        $this->assertSame($first->id, $this->storedJson($second)['previous_document_id']);
        $this->assertNull($this->storedJson($second)['answers']['quote_id']);
    }
}
