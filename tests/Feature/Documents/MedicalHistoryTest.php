<?php

namespace Tests\Feature\Documents;

use App\Enums\ClinicalFileType;
use App\Enums\DocumentTemplate;
use App\Models\AuditLog;
use App\Models\ClinicalFile;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Documents\DocumentService;
use App\Support\CurrentOrganization;
use App\Support\Ui;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MedicalHistoryTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMember $doctor;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->doctor = $this->member(doctor: true);
        $this->patient = $this->createPatient($this->doctor, [
            'full_name' => 'LAURA GOMEZ', 'sex' => 'female', 'assigned_doctor_member_id' => $this->doctor->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'patient_id' => $this->patient->id,
            'doctor_member_id' => $this->doctor->id,
            'address' => 'Av. Juárez 10, Centro',
            'occupation' => 'Ingeniera',
            'chief_complaint' => 'Dolor en molar inferior derecho',
            'bad_previous_experience' => '0',
            'bad_experience_reason' => 'no debería guardarse',
            'uses_toothbrush' => '1',
            'brushings_per_day' => '3',
            'extraction_complications' => ['hemorragia_prolongada'],
            'under_medical_treatment' => 'Metformina 850 mg',
            'conditions' => ['diabetes', 'reacciones_alergicas'],
            'allergies' => 'Penicilina',
            'blood_pressure' => '120 / 80',
            'pulse' => '72',
            'respiration' => '16',
            ...$overrides,
        ];
    }

    private function storedJson(ClinicalFile $document): array
    {
        return json_decode(Storage::disk('local')->get($document->documentDirectory().'/respuestas.json'), true);
    }

    public function test_form_shows_patient_data_and_prefills_allergies_and_doctor(): void
    {
        MedicalRecord::where('patient_id', $this->patient->id)->update(['allergies' => 'LÁTEX']);

        $this->actingAsMember($this->doctor)
            ->get('/documentos/historia-clinica/crear?paciente='.$this->patient->id)
            ->assertOk()
            ->assertSee('LAURA GOMEZ')
            ->assertSee('LÁTEX')
            ->assertSee('Embarazada')
            ->assertViewHas('form', fn ($form) => $form['doctor_member_id'] === $this->doctor->id && $form['allergies'] === 'LÁTEX');

        $male = $this->createPatient($this->doctor, ['sex' => 'male']);
        $this->actingAsMember($this->doctor)
            ->get('/documentos/historia-clinica/crear?paciente='.$male->id)
            ->assertOk()
            ->assertDontSee('Embarazada');
    }

    public function test_form_requires_an_active_patient_of_the_clinic(): void
    {
        $inactive = $this->createPatient($this->doctor);
        $inactive->update(['status' => 'inactive']);
        $other = $this->member();
        $foreign = $this->createPatient($other);

        foreach (['', $inactive->id, $foreign->id] as $id) {
            $this->actingAsMember($this->doctor)
                ->get('/documentos/historia-clinica/crear?paciente='.$id)
                ->assertRedirect(route('documents.create'))
                ->assertSessionHas('toast.type', 'error');
        }
    }

    public function test_generates_pdf_and_json_without_touching_the_medical_record(): void
    {
        $response = $this->actingAsMember($this->doctor)->post('/documentos/historia-clinica', $this->payload());

        $document = ClinicalFile::sole();
        $response->assertRedirect(route('documents.show', $document))
            ->assertSessionHas('toast.title', 'Documento generado correctamente');

        $this->assertSame(DocumentTemplate::MedicalHistory, $document->template());
        $this->assertSame(ClinicalFileType::Other, $document->file_type);
        $this->assertSame("Historia Clínica Dental — {$this->patient->folio} — ".Ui::date(today()), $document->display_name);
        $this->assertMatchesRegularExpression("#^org/{$this->doctor->organization_id}/documentos/historia-clinica/{$document->id}/documento\.pdf$#", $document->storage_key);
        $this->assertSame($this->doctor->id, $document->uploaded_by_member_id);

        $pdf = Storage::disk('local')->get($document->storage_key);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(0, $document->size_bytes);
        $this->assertSame(strlen($pdf), $document->size_bytes);
        $this->assertSame(hash('sha256', $pdf), $document->sha256);

        $json = $this->storedJson($document);
        $this->assertNull($json['previous_document_id']);
        $this->assertSame([
            'doctor_member_id' => $this->doctor->id,
            'address' => 'Av. Juárez 10, Centro',
            'occupation' => 'Ingeniera',
            'chief_complaint' => 'Dolor en molar inferior derecho',
            'bad_previous_experience' => false,
            'bad_experience_reason' => null,
            'uses_toothbrush' => true,
            'brushings_per_day' => 3,
            'extraction_complications' => ['hemorragia_prolongada'],
            'under_medical_treatment' => 'Metformina 850 mg',
            'conditions' => ['diabetes', 'reacciones_alergicas'],
            'allergies' => 'Penicilina',
            'blood_pressure' => '120/80',
            'pulse' => 72,
            'respiration' => 16,
        ], $json['answers']);

        // El expediente no se toca: alergias y antecedentes se capturan a mano en el Expediente (sin resúmenes automáticos).
        $record = MedicalRecord::where('patient_id', $this->patient->id)->sole();
        $this->assertNull($record->allergies);
        $this->assertNull($record->medical_history);

        $this->assertTrue(AuditLog::where('entity_id', $document->id)->where('action', 'insert')->exists());
        $this->assertFalse(AuditLog::where('entity_id', $record->id)->where('action', 'update')->exists());
        $this->assertStringNotContainsString('Penicilina', AuditLog::pluck('changed_fields')->toJson());

        $this->actingAsMember($this->doctor)->get(route('documents.show', $document))->assertOk()->assertSee('Generado');
    }

    public function test_conditional_validations(): void
    {
        $nonDoctor = $this->member($this->doctor->organization);
        $cases = [
            [['bad_previous_experience' => '1', 'bad_experience_reason' => ''], 'bad_experience_reason', 'Indica por qué fue desagradable la experiencia.'],
            [['allergies' => ''], 'allergies', 'Describe las alergias del paciente.'],
            [['blood_pressure' => '120-80'], 'blood_pressure', 'La presión arterial debe tener el formato 120/80.'],
            [['pulse' => '300'], 'pulse', 'El pulso debe estar entre 30 y 220 lpm.'],
            [['respiration' => '2'], 'respiration', 'La respiración debe estar entre 5 y 60 rpm.'],
            [['brushings_per_day' => '11'], 'brushings_per_day', 'Las veces al día deben estar entre 0 y 10.'],
            [['chief_complaint' => '  '], 'chief_complaint', null],
            [['conditions' => ['volar']], 'conditions.0', null],
            [['doctor_member_id' => $nonDoctor->id], 'doctor_member_id', 'El doctor seleccionado no es válido.'],
            [['bad_previous_experience' => null], 'bad_previous_experience', 'Indica si tuvo una experiencia desagradable.'],
        ];

        foreach ($cases as [$overrides, $field, $message]) {
            $this->actingAsMember($this->doctor)
                ->post('/documentos/historia-clinica', $this->payload($overrides))
                ->assertSessionHasErrors($message ? [$field => $message] : [$field], null, 'medicalHistory');
        }

        // Sin reacciones alérgicas, las alergias son opcionales.
        $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica', $this->payload(['conditions' => [], 'allergies' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ClinicalFile::count());
        $this->assertNull(MedicalRecord::where('patient_id', $this->patient->id)->value('allergies'));
    }

    public function test_pregnancy_only_applies_to_female_patients(): void
    {
        $male = $this->createPatient($this->doctor, ['sex' => 'male']);

        $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica', $this->payload(['patient_id' => $male->id, 'conditions' => ['embarazo']]))
            ->assertSessionHasErrors(['conditions' => 'El embarazo solo aplica a pacientes de sexo femenino.'], null, 'medicalHistory');

        $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica', $this->payload(['conditions' => ['embarazo']]))
            ->assertSessionHasNoErrors();
    }

    public function test_preview_renders_the_pdf_without_saving_anything(): void
    {
        $response = $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica/vista-previa', $this->payload())
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(0, ClinicalFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull(MedicalRecord::where('patient_id', $this->patient->id)->value('allergies'));
    }

    public function test_new_version_is_prefilled_from_the_previous_answers(): void
    {
        $this->actingAsMember($this->doctor)->post('/documentos/historia-clinica', $this->payload());
        $first = ClinicalFile::sole();
        $newVersionUrl = route('documents.medical-history.create', ['paciente' => $this->patient->id, 'desde' => $first->id]);

        $this->actingAsMember($this->doctor)->get(route('documents.show', $first))->assertSee($newVersionUrl);

        $this->actingAsMember($this->doctor)->get($newVersionUrl)
            ->assertOk()
            ->assertSee('Nueva versión')
            ->assertViewHas('form', fn ($form) => $form['occupation'] === 'Ingeniera'
                && $form['conditions'] === ['diabetes', 'reacciones_alergicas']
                && $form['pulse'] === 72);

        $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica', $this->payload(['occupation' => 'Arquitecta', 'previous_document_id' => $first->id]))
            ->assertSessionHasNoErrors();

        $second = ClinicalFile::whereKeyNot($first->id)->sole();
        $this->assertSame($first->id, $this->storedJson($second)['previous_document_id']);
        $this->assertSame('Arquitecta', $this->storedJson($second)['answers']['occupation']);
        $this->assertSame('Ingeniera', $this->storedJson($first)['answers']['occupation']);
    }

    public function test_patient_of_another_clinic_cannot_be_used(): void
    {
        $other = $this->member();
        $foreign = $this->createPatient($other);

        $this->actingAsMember($this->doctor)
            ->post('/documentos/historia-clinica', $this->payload(['patient_id' => $foreign->id]))
            ->assertSessionHasErrors(['patient_id' => 'El paciente no existe o está inactivo.'], null, 'medicalHistory');
        $this->assertSame(0, ClinicalFile::withoutGlobalScopes()->count());
    }

    public function test_written_files_are_removed_when_generation_fails(): void
    {
        app(CurrentOrganization::class)->set($this->doctor);

        try {
            app(DocumentService::class)->generate(
                DocumentTemplate::MedicalHistory, $this->patient, $this->doctor, ['chief_complaint' => 'X'],
                afterCreate: fn () => throw new RuntimeException('falla simulada'),
            );
            $this->fail('La generación debió fallar.');
        } catch (RuntimeException $e) {
            $this->assertSame('falla simulada', $e->getMessage());
        }

        $this->assertSame(0, ClinicalFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
