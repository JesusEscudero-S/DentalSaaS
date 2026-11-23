<?php

namespace Tests\Feature\Records;

use App\Enums\ClinicalFileType;
use App\Models\ClinicalFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** RF 6.7 Subir / RF 6.8 Eliminar radiografías y archivos. */
class ClinicalFileTest extends RecordTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ClinicalFile::DISK);   // nada se escribe en el storage real
    }

    /**
     * PNG real de 1×1 píxel. No se usa UploadedFile::fake()->image() porque necesita la
     * extensión GD de PHP, que XAMPP no trae activada.
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    private function upload(array $files, array $data = [])
    {
        return $this->actingAsMember($this->staff)->post($this->url('archivos'), [
            'files' => $files,
            'file_type' => 'radiografia',
            ...$data,
        ]);
    }

    public function test_uploads_images_and_pdfs_to_the_private_disk(): void
    {
        $this->upload([
            $this->png('panoramica.png'),
            UploadedFile::fake()->create('estudio.pdf', 300, 'application/pdf'),
        ])->assertSessionHas('toast.title', 'Archivos cargados');   // mensaje del PDF

        $files = ClinicalFile::withoutGlobalScopes()->orderBy('display_name')->get();
        $this->assertSame(['estudio.pdf', 'panoramica.png'], $files->pluck('display_name')->all());

        foreach ($files as $file) {
            $this->assertSame(ClinicalFileType::XRay, $file->file_type);
            $this->assertStringStartsWith("clinical/{$this->patient->organization_id}/{$this->patient->id}/", $file->storage_key);
            $this->assertSame(64, strlen($file->sha256));
            Storage::disk(ClinicalFile::DISK)->assertExists($file->storage_key);
        }

        $this->actingAsMember($this->staff)->get($this->url('archivos'))->assertSee('panoramica.png')->assertSee('PDF');
    }

    public function test_short_description_becomes_the_display_name(): void
    {
        $this->upload([$this->png('IMG_0001.png')], ['description' => 'Foto intraoral pieza 26', 'file_type' => 'fotografia_intraoral']);

        $this->assertSame('Foto intraoral pieza 26', ClinicalFile::withoutGlobalScopes()->value('display_name'));
    }

    public function test_only_jpg_png_or_pdf_and_limited_size(): void
    {
        $this->upload([UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors(['files.0' => 'Solo se aceptan archivos JPG, PNG o PDF.'], null, 'uploadFile');

        $this->upload([UploadedFile::fake()->create('enorme.pdf', ClinicalFile::MAX_KB + 1, 'application/pdf')])
            ->assertSessionHasErrors(['files.0'], null, 'uploadFile');

        $this->upload([])->assertSessionHasErrors(['files'], null, 'uploadFile');
        $this->upload([$this->png('a.png')], ['file_type' => 'video'])->assertSessionHasErrors(['file_type'], null, 'uploadFile');

        $this->assertSame(0, ClinicalFile::withoutGlobalScopes()->count());
    }

    public function test_download_goes_through_the_app_and_is_scoped_to_the_patient(): void
    {
        $this->upload([UploadedFile::fake()->create('estudio.pdf', 50, 'application/pdf')]);
        $file = ClinicalFile::withoutGlobalScopes()->sole();

        $this->actingAsMember($this->staff)->get($this->url("archivos/{$file->id}"))->assertOk()->assertDownload();

        $other = $this->createPatient($this->staff);
        $this->actingAsMember($this->staff)->get($this->url("archivos/{$file->id}", $other))->assertNotFound();

        $this->get($this->url("archivos/{$file->id}"))->assertOk();   // con sesión sí
        auth()->logout();
        $this->get($this->url("archivos/{$file->id}"))->assertRedirect('/login');
    }

    public function test_delete_is_logical(): void
    {
        $this->upload([$this->png('rx.png')]);
        $file = ClinicalFile::withoutGlobalScopes()->sole();

        $this->actingAsMember($this->doctor)->delete($this->url("archivos/{$file->id}"))
            ->assertSessionHas('toast.title', 'Archivo eliminado');   // mensaje del PDF

        $this->assertSoftDeleted('clinical_files', ['id' => $file->id]);
        Storage::disk(ClinicalFile::DISK)->assertExists($file->storage_key);   // el archivo físico se conserva
        $this->actingAsMember($this->staff)->get($this->url('archivos'))->assertSee('Sin archivos');
        $this->actingAsMember($this->staff)->get($this->url("archivos/{$file->id}"))->assertNotFound();
    }
}
