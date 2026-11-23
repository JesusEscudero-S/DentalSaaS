<?php

namespace Tests\Feature\Documents;

use App\Enums\ClinicalFileType;
use App\Enums\ConsentMethod;
use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\ClinicalFile;
use App\Models\ConsentAcceptance;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Documents\DocumentService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMember $doctor;

    private Patient $patient;

    private ClinicalFile $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->doctor = $this->member(doctor: true);
        $this->patient = $this->createPatient($this->doctor);
        $this->document = $this->authorization($this->doctor, $this->patient);
    }

    private function authorization(OrganizationMember $doctor, Patient $patient): ClinicalFile
    {
        app(CurrentOrganization::class)->set($doctor);

        return app(DocumentService::class)->generateTreatmentAuthorization($patient, $doctor->load('user', 'doctorProfile'), [
            'doctor_member_id' => $doctor->id, 'fluoride' => [], 'diagnoses' => [], 'activities' => [], 'quote_id' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'method' => ConsentMethod::ScannedSignature->value,
            'accepted_by_name' => '  maría   lópez ',
            'accepted_at' => now()->format('Y-m-d\TH:i'),
            'signed_file' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\n% documento firmado y escaneado\n"),
            ...$overrides,
        ];
    }

    /** Aceptaciones de documentos (createPatient ya registra la responsiva del alta, sin documento). */
    private function documentAcceptances()
    {
        return ConsentAcceptance::withoutGlobalScopes()->whereNotNull('clinical_file_id');
    }

    private function sign(array $overrides = [], ?ClinicalFile $document = null)
    {
        return $this->actingAsMember($this->doctor)->post(route('documents.consent.store', $document ?? $this->document), $this->payload($overrides));
    }

    public function test_registers_a_scanned_signature_with_the_document_hash(): void
    {
        $this->sign()
            ->assertRedirect(route('documents.show', $this->document))
            ->assertSessionHas('toast.title', 'Firma registrada correctamente');

        $acceptance = ConsentAcceptance::where('clinical_file_id', $this->document->id)->sole();
        $this->assertSame($this->document->sha256, $acceptance->content_hash);
        $this->assertSame('MARÍA LÓPEZ', $acceptance->accepted_by_name);
        $this->assertSame(ConsentMethod::ScannedSignature, $acceptance->method);
        $this->assertSame($this->patient->id, $acceptance->patient_id);
        $this->assertTrue(AuditLog::where('entity_id', $acceptance->id)->where('action', 'insert')->exists());

        $scan = $this->document->signedScan();
        $this->assertNotNull($scan);
        $this->assertSame(ClinicalFileType::Consent, $scan->file_type);
        $this->assertStringStartsWith($this->document->documentDirectory().'/firmado-', $scan->storage_key);
        $this->assertStringEndsWith('.pdf', $scan->storage_key);
        Storage::disk('local')->assertExists($scan->storage_key);
        $this->assertNull($scan->template());
        $this->assertSame(1, ClinicalFile::documents()->count());

        $this->assertSame(DocumentStatus::Signed, $this->document->fresh()->documentStatus());
        $this->actingAsMember($this->doctor)->get(route('documents.show', $this->document))
            ->assertOk()
            ->assertSee('MARÍA LÓPEZ')
            ->assertSee('Ver escaneo')
            ->assertDontSee('Registrar firma');
        $this->actingAsMember($this->doctor)->get(route('documents.consent.scan', $this->document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_second_signature_is_not_allowed(): void
    {
        $this->sign()->assertSessionHasNoErrors();

        $this->sign(['accepted_by_name' => 'Otra persona'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Este consentimiento ya tiene una firma registrada.']);

        $this->assertSame(1, $this->documentAcceptances()->count());
        $this->assertCount(1, collect(Storage::disk('local')->allFiles())->filter(fn ($path) => str_contains($path, '/firmado-')));
    }

    public function test_scanned_signature_requires_the_file(): void
    {
        $this->sign(['signed_file' => null])
            ->assertSessionHasErrors(['signed_file' => 'Sube el documento firmado (PDF, JPG o PNG).'], null, 'registerSignature');
        $this->sign(['signed_file' => UploadedFile::fake()->createWithContent('firma.docx', 'texto plano')])
            ->assertSessionHasErrors(['signed_file' => 'El documento firmado debe ser PDF, JPG o PNG.'], null, 'registerSignature');

        $this->assertSame(0, $this->documentAcceptances()->count());
    }

    public function test_verbal_acceptance_requires_a_witness(): void
    {
        $verbal = ['method' => ConsentMethod::Verbal->value, 'signed_file' => null];

        $this->sign($verbal)
            ->assertSessionHasErrors(['witness_member_id' => 'La aceptación verbal requiere un testigo.'], null, 'registerSignature');

        $witness = $this->member($this->doctor->organization);
        $this->sign([...$verbal, 'witness_member_id' => $witness->id])->assertSessionHasNoErrors();

        $acceptance = $this->documentAcceptances()->sole();
        $this->assertSame($witness->id, $acceptance->witness_member_id);
        $this->assertNull($this->document->signedScan());
        $this->actingAsMember($this->doctor)->get(route('documents.show', $this->document))->assertSee($witness->displayName());
    }

    public function test_signature_date_must_be_between_the_document_and_now(): void
    {
        $this->sign(['accepted_at' => now()->addHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['accepted_at' => 'La fecha de firma no puede ser posterior a este momento.'], null, 'registerSignature');
        $this->sign(['accepted_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['accepted_at' => 'La fecha de firma no puede ser anterior a la generación del documento.'], null, 'registerSignature');

        $this->assertSame(0, $this->documentAcceptances()->count());
    }

    public function test_a_medical_history_cannot_be_signed(): void
    {
        app(CurrentOrganization::class)->set($this->doctor);
        $history = app(DocumentService::class)->generateMedicalHistory($this->patient, $this->doctor->load('user', 'doctorProfile'), ['chief_complaint' => 'Revisión']);

        $this->sign([], $history)
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Este documento no requiere firma.']);
        $this->assertSame(0, $this->documentAcceptances()->count());
    }

    public function test_documents_of_another_clinic_cannot_be_signed(): void
    {
        $other = $this->member(doctor: true);
        $foreign = $this->authorization($other, $this->createPatient($other));

        $this->sign([], $foreign)->assertNotFound();
        $this->actingAsMember($this->doctor)->get(route('documents.consent.scan', $foreign))->assertNotFound();
        $this->assertSame(0, $this->documentAcceptances()->count());
    }
}
