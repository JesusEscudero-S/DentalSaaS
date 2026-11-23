<?php

namespace App\Http\Requests\Documents;

use App\Enums\ConsentMethod;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Registro de la firma o aceptación de una Autorización de tratamiento. */
class StoreConsentRequest extends FormRequest
{
    protected $errorBag = 'registerSignature';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('accepted_by_name');

        $this->merge([
            'accepted_by_name' => is_string($name) && trim($name) !== '' ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($name))) : null,
            'witness_member_id' => $this->input('witness_member_id') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(ConsentMethod::class)],
            'accepted_by_name' => ['required', 'string', 'max:250'],
            'accepted_at' => ['required', 'date', 'before_or_equal:now'],
            'witness_member_id' => [
                Rule::requiredIf(fn () => $this->input('method') === ConsentMethod::Verbal->value),
                'nullable', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! OrganizationMember::where('is_active', true)->whereKey($value)->exists()) {
                        $fail('El testigo seleccionado no es válido.');
                    }
                },
            ],
            'signed_file' => [
                Rule::requiredIf(fn () => $this->input('method') === ConsentMethod::ScannedSignature->value),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240',
            ],
        ];
    }

    /** La firma no puede ser anterior a la generación del documento. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $document = $this->route('document');
            if (! $document instanceof ClinicalFile || $validator->errors()->has('accepted_at')) {
                return;
            }

            if (Carbon::parse($this->input('accepted_at'))->lt($document->created_at->copy()->startOfMinute())) {
                $validator->errors()->add('accepted_at', 'La fecha de firma no puede ser anterior a la generación del documento.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accepted_at.before_or_equal' => 'La fecha de firma no puede ser posterior a este momento.',
            'witness_member_id.required' => 'La aceptación verbal requiere un testigo.',
            'signed_file.required' => 'Sube el documento firmado (PDF, JPG o PNG).',
            'signed_file.file' => 'No se pudo subir el documento firmado.',
            'signed_file.uploaded' => 'No se pudo subir el documento firmado.',
            'signed_file.mimes' => 'El documento firmado debe ser PDF, JPG o PNG.',
            'signed_file.max' => 'El documento firmado no debe pesar más de 10 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'method' => 'método',
            'accepted_by_name' => 'nombre de quien firma',
            'accepted_at' => 'fecha de firma',
            'witness_member_id' => 'testigo',
        ];
    }
}
