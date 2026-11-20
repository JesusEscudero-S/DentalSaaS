<?php

namespace Tests\Feature\Documents;

use App\Enums\ClinicalFileType;
use App\Enums\DocumentTemplate;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\ToothFinding;
use App\Models\Treatment;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalModelsTest extends TestCase
{
    use RefreshDatabase;

    private function file(OrganizationMember $member, Patient $patient, string $storageKey): ClinicalFile
    {
        app(CurrentOrganization::class)->set($member);

        return ClinicalFile::create([
            'patient_id' => $patient->id,
            'file_type' => ClinicalFileType::Other,
            'display_name' => 'Documento',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_key' => $storageKey,
            'sha256' => hash('sha256', $storageKey),
            'uploaded_by_member_id' => $member->id,
        ]);
    }

    private function documentKey(OrganizationMember $member, DocumentTemplate $template): string
    {
        return "org/{$member->organization_id}/documentos/{$template->value}/".Str::uuid().'/documento.pdf';
    }

    public function test_clinical_files_are_isolated_by_organization(): void
    {
        $member = $this->member();
        $other = $this->member();
        $mine = $this->file($member, $this->createPatient($member), $this->documentKey($member, DocumentTemplate::MedicalHistory));
        $this->file($other, $this->createPatient($other), $this->documentKey($other, DocumentTemplate::MedicalHistory));

        app(CurrentOrganization::class)->set($member);

        $this->assertSame([$mine->id], ClinicalFile::pluck('id')->all());
        $this->assertSame($member->organization_id, $mine->organization_id);
    }

    public function test_template_is_derived_from_storage_key(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $history = $this->file($member, $patient, $this->documentKey($member, DocumentTemplate::MedicalHistory));
        $authorization = $this->file($member, $patient, $this->documentKey($member, DocumentTemplate::TreatmentAuthorization));
        $scan = $this->file($member, $patient, $authorization->documentDirectory().'/firmado-'.Str::uuid().'.pdf');
        $xray = $this->file($member, $patient, "org/{$member->organization_id}/radiografias/rx.png");

        $this->assertSame(DocumentTemplate::MedicalHistory, $history->template());
        $this->assertSame(DocumentTemplate::TreatmentAuthorization, $authorization->template());
        $this->assertNull($scan->template());
        $this->assertNull($xray->template());

        $this->assertEqualsCanonicalizing([$history->id, $authorization->id], ClinicalFile::documents()->pluck('id')->all());
    }

    public function test_deleted_files_are_hidden(): void
    {
        $member = $this->member();
        $file = $this->file($member, $this->createPatient($member), $this->documentKey($member, DocumentTemplate::MedicalHistory));

        $file->delete();

        $this->assertNotNull($file->fresh()->deleted_at);
        $this->assertSame(0, ClinicalFile::count());
    }

    public function test_odontogram_and_treatment_models_are_isolated(): void
    {
        $member = $this->member();
        $other = $this->member();

        foreach ([$member, $other] as $m) {
            $patient = $this->createPatient($m);
            $treatment = Treatment::create(['name' => 'Resina', 'duration_minutes' => 30, 'price' => '800']);
            PatientTreatment::create(['patient_id' => $patient->id, 'treatment_id' => $treatment->id, 'tooth_fdi' => 16, 'status' => 'en_progreso']);
            ToothFinding::create(['patient_id' => $patient->id, 'tooth_fdi' => 16, 'dentition' => 'permanente', 'condition' => 'caries', 'author_member_id' => $m->id]);
            ToothFinding::create(['patient_id' => $patient->id, 'tooth_fdi' => 11, 'dentition' => 'permanente', 'condition' => 'sano', 'author_member_id' => $m->id, 'superseded_at' => now()]);
        }

        app(CurrentOrganization::class)->set($member);

        $this->assertSame(1, Treatment::count());
        $this->assertSame('Resina', PatientTreatment::sole()->treatment->name);
        $this->assertSame([16], ToothFinding::current()->pluck('tooth_fdi')->all());
    }
}
