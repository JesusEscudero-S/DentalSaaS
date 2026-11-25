<?php

namespace App\Http\Requests\Treatments;

use Illuminate\Foundation\Http\FormRequest;

/** Módulo 7, RN-T4: nota de corrección de una sesión. */
class SessionNoteRequest extends FormRequest
{
    protected $errorBag = 'sessionNote';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Escribe la corrección.'];
    }
}
