<?php

namespace App\Http\Requests\Records;

use App\Enums\ClinicalFileType;
use App\Models\ClinicalFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF 6.7 Subir radiografías / archivos: solo JPG, PNG o PDF y con tamaño máximo.
 * `mimes` revisa el contenido real del archivo, no solo la extensión del nombre.
 */
class StoreClinicalFileRequest extends FormRequest
{
    protected $errorBag = 'uploadFile';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.ClinicalFile::MAX_KB],
            'file_type' => ['required', Rule::enum(ClinicalFileType::class)],
            'description' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'files.required' => 'Selecciona al menos un archivo.',
            'files.max' => 'Puedes subir hasta 10 archivos a la vez.',
            'files.*.mimes' => 'Solo se aceptan archivos JPG, PNG o PDF.',
            'files.*.max' => 'Cada archivo debe pesar como máximo 10 MB.',
            'files.*.file' => 'No se pudo recibir el archivo. Intenta de nuevo.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file_type' => 'tipo de archivo', 'description' => 'descripción'];
    }
}
