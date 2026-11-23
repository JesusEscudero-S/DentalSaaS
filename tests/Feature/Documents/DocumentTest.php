<?php

namespace Tests\Feature\Documents;

use App\Enums\ClinicalFileType;
use App\Enums\ConsentMethod;
use App\Enums\DocumentTemplate;
use App\Models\AuditLog;
use App\Models\ClinicalFile;
use App\Models\ConsentAcceptance;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Documento de plantilla con su PDF en disco (sin pasar por DocumentService::generate). */
    private function document(OrganizationMember $member, Patient $patient, DocumentTemplate $template, array $attributes = []): ClinicalFile
    {
        app(CurrentOrganization::class)->set($member);
        $id = (string) Str::uuid();
        $key = "org/{$member->organization_id}/documentos/{$template->value}/{$id}/documento.pdf";
        $content = '%PDF-1.4 documento de prueba '.$id;
        Storage::disk('local')->put($key, $content);

        return tap(new ClinicalFile([
            'patient_id' => $patient->id,
            'file_type' => $template->fileType(),
            'display_name' => "{$template->label()} — {$patient->folio}",
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'storage_key' => $key,
            'sha256' => hash('sha256', $content),
            'uploaded_by_member_id' => $member->id,
            ...$attributes,
        ]), function (ClinicalFile $file) use ($id) {
            $file->id = $id;
            $file->save();
        });
    }

    private function sign(OrganizationMember $member, ClinicalFile $document): ConsentAcceptance
    {
        app(CurrentOrganization::class)->set($member);

        return ConsentAcceptance::create([
            'patient_id' => $document->patient_id,
            'clinical_file_id' => $document->id,
            'accepted_at' => now(),
            'accepted_by_name' => 'TUTOR DE PRUEBA',
            'method' => ConsentMethod::ScannedSignature,
            'content_hash' => $document->sha256,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/documentos')->assertRedirect('/login');
    }

    public function test_history_lists_only_template_documents_with_their_status(): void
    {
        $member = $this->member();
        $ana = $this->createPatient($member, ['full_name' => 'ANA LOPEZ']);
        $beto = $this->createPatient($member, ['full_name' => 'BETO RUIZ']);
        $history = $this->document($member, $ana, DocumentTemplate::MedicalHistory);
        $pending = $this->document($member, $ana, DocumentTemplate::TreatmentAuthorization);
        $signed = $this->document($member, $beto, DocumentTemplate::TreatmentAuthorization);
        $this->sign($member, $signed);
        $this->document($member, $beto, DocumentTemplate::MedicalHistory)->delete();
        ClinicalFile::create([
            'patient_id' => $ana->id, 'file_type' => ClinicalFileType::XRay, 'display_name' => 'Radiografía', 'mime_type' => 'image/png',
            'size_bytes' => 10, 'storage_key' => "org/{$member->organization_id}/radiografias/rx.png", 'uploaded_by_member_id' => $member->id,
        ]);

        $this->actingAsMember($member)->get('/documentos')
            ->assertOk()
            ->assertViewHas('documents', fn ($page) => $page->total() === 3)
            ->assertSee('Pendiente de firma')
            ->assertSee('Firmado')
            ->assertSee('Generado');

        $this->actingAsMember($member)->get('/documentos?estado=pendiente')
            ->assertViewHas('documents', fn ($page) => $page->pluck('id')->all() === [$pending->id]);
        $this->actingAsMember($member)->get('/documentos?estado=firmado')
            ->assertViewHas('documents', fn ($page) => $page->pluck('id')->all() === [$signed->id]);
        $this->actingAsMember($member)->get('/documentos?plantilla=historia-clinica')
            ->assertViewHas('documents', fn ($page) => $page->pluck('id')->all() === [$history->id]);
        $this->actingAsMember($member)->get('/documentos?q='.$beto->folio)
            ->assertViewHas('documents', fn ($page) => $page->pluck('id')->all() === [$signed->id]);
        $this->actingAsMember($member)->get('/documentos?paciente='.$ana->id)
            ->assertSee('ANA LOPEZ ('.$ana->folio.')')
            ->assertViewHas('documents', fn ($page) => $page->total() === 2);
    }

    public function test_empty_history_shows_empty_state(): void
    {
        $this->actingAsMember($this->member())->get('/documentos')->assertOk()->assertSee('Aún no hay documentos generados');
    }

    public function test_template_cards_and_patient_picker_search(): void
    {
        $member = $this->member();
        $active = $this->createPatient($member, ['full_name' => 'CARLA MENDOZA']);
        $inactive = $this->createPatient($member, ['full_name' => 'CARLA INACTIVA']);
        $inactive->update(['status' => 'inactive']);
        $other = $this->member();
        $this->createPatient($other, ['full_name' => 'CARLA AJENA']);

        $this->actingAsMember($member)->get('/documentos/nuevo')
            ->assertOk()
            ->assertSee('Historia Clínica Dental')
            ->assertSee('Autorización de Tratamiento')
            ->assertViewHas('preselected', null);

        $this->actingAsMember($member)->get('/documentos/nuevo?paciente='.$active->id)
            ->assertViewHas('preselected', fn ($p) => $p['id'] === $active->id && $p['folio'] === $active->folio);
        $this->actingAsMember($member)->get('/documentos/nuevo?paciente='.$inactive->id)->assertViewHas('preselected', null);

        $this->actingAsMember($member)->getJson('/documentos/pacientes?q=carla')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $active->id)
            ->assertJsonPath('0.name', 'CARLA MENDOZA');
    }

    public function test_preview_renders_the_blank_template_as_pdf(): void
    {
        $member = $this->member();

        foreach (DocumentTemplate::cases() as $template) {
            $response = $this->actingAsMember($member)->get(route('documents.preview', $template))->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $this->actingAsMember($member)->get('/documentos/plantillas/receta/vista-previa')->assertNotFound();
    }

    public function test_detail_download_and_stream(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);
        $document = $this->document($member, $patient, DocumentTemplate::TreatmentAuthorization);

        $this->actingAsMember($member)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee($document->display_name)
            ->assertSee('Pendiente de firma')
            ->assertSee(substr($document->sha256, 0, 16))
            ->assertSee(route('documents.stream', $document));

        $this->actingAsMember($member)->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload("autorizacion-tratamiento-{$patient->folio}-".today()->toDateString().'.pdf');

        $stream = $this->actingAsMember($member)->get(route('documents.stream', $document))->assertOk();
        $this->assertStringStartsWith('inline;', $stream->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $stream->headers->get('Content-Type'));
    }

    public function test_unsigned_document_is_soft_deleted(): void
    {
        $member = $this->member();
        $document = $this->document($member, $this->createPatient($member), DocumentTemplate::TreatmentAuthorization);

        $this->actingAsMember($member)->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('toast.title', 'Documento eliminado');

        $this->assertNotNull(ClinicalFile::withTrashed()->find($document->id)->deleted_at);
        Storage::disk('local')->assertExists($document->storage_key);
        $this->assertTrue(AuditLog::where('entity_id', $document->id)->where('action', 'delete')->exists());
        $this->actingAsMember($member)->get(route('documents.show', $document))->assertNotFound();
    }

    public function test_signed_consent_cannot_be_deleted(): void
    {
        $member = $this->member();
        $document = $this->document($member, $this->createPatient($member), DocumentTemplate::TreatmentAuthorization);
        $this->sign($member, $document);

        $this->actingAsMember($member)->get(route('documents.show', $document))->assertSee('TUTOR DE PRUEBA')->assertSee('Firmado');
        $this->actingAsMember($member)->delete(route('documents.destroy', $document))
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'No se puede eliminar un consentimiento firmado']);

        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_documents_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->document($other, $this->createPatient($other), DocumentTemplate::MedicalHistory);

        $member = $this->member();
        foreach (['documents.show', 'documents.download', 'documents.stream'] as $route) {
            $this->actingAsMember($member)->get(route($route, $foreign))->assertNotFound();
        }
        $this->actingAsMember($member)->delete(route('documents.destroy', $foreign))->assertNotFound();
        $this->assertNull(ClinicalFile::withoutGlobalScopes()->find($foreign->id)->deleted_at);
    }

    public function test_files_that_are_not_template_documents_are_not_shown_here(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);
        $xray = ClinicalFile::create([
            'patient_id' => $patient->id, 'file_type' => ClinicalFileType::XRay, 'display_name' => 'Radiografía', 'mime_type' => 'image/png',
            'size_bytes' => 10, 'storage_key' => "org/{$member->organization_id}/radiografias/rx.png", 'uploaded_by_member_id' => $member->id,
        ]);

        $this->actingAsMember($member)->get(route('documents.show', $xray))->assertNotFound();
        $this->actingAsMember($member)->delete(route('documents.destroy', $xray))->assertNotFound();
    }

    public function test_patient_profile_links_to_their_documents(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $this->actingAsMember($member)->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee(route('documents.index', ['paciente' => $patient->id]), false);
    }
}
