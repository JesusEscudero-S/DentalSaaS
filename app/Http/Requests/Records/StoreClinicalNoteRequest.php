<?php

namespace App\Http\Requests\Records;

use App\Enums\AppointmentStatus;
use App\Enums\Dentition;
use App\Enums\NoteType;
use App\Models\ClinicalNote;
use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF 6.4 Registrar diagnóstico dental (nota clínica simple o SOAP).
 *
 * El botón que se presionó llega en "action": draft (Guardar borrador) o sign (Firmar nota).
 * Para firmar, la nota debe estar completa; un borrador puede guardarse incompleto.
 */
class StoreClinicalNoteRequest extends FormRequest
{
    protected $errorBag = 'createNote';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    public function wantsToSign(): bool
    {
        return $this->input('action') === 'sign';
    }

    public function isSoap(): bool
    {
        return $this->input('note_type') === NoteType::Soap->value;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $patient = $this->route('patient');

        return [
            'note_type' => ['required', Rule::enum(NoteType::class)],
            'action' => ['required', 'in:draft,sign'],
            'doctor_member_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                        $fail('El doctor seleccionado no es válido.');
                    }
                },
            ],
            // PDF 6.4: la fecha del diagnóstico no puede ser futura.
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            // Cita opcional: del mismo paciente y ya iniciada o terminada.
            'appointment_id' => [
                'nullable', 'uuid',
                Rule::exists('appointments', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('patient_id', $patient->id)
                    ->whereIn('status', [AppointmentStatus::EnCurso->value, AppointmentStatus::Completada->value]),
            ],
            'treatment_id' => [
                'nullable', 'uuid',
                Rule::exists('treatments', 'id')->where('organization_id', $organizationId)->where('status', 'active'),
            ],
            // Nomenclatura FDI estricta (PDF 6.3): solo piezas que existen.
            'tooth_fdi' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Dentition::forTooth((int) $value) === null) {
                        $fail('La pieza dental no es válida (numeración FDI: 11-48 o 51-85).');
                    }
                },
            ],
            // Nota simple: la descripción clínica es obligatoria (PDF 6.4). En SOAP es la sección O.
            'objective' => [$this->isSoap() ? 'nullable' : 'required', 'string', 'max:5000'],
            'subjective' => ['nullable', 'string', 'max:5000'],
            'analysis' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Para firmar una nota SOAP, las 4 secciones son obligatorias. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->isSoap() || ! $this->wantsToSign()) {
                return;
            }

            foreach (ClinicalNote::SOAP_SECTIONS as $field => [$label]) {
                if (blank($this->input($field))) {
                    $validator->errors()->add($field, "Para firmar la nota completa la sección {$label}.");
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'objective.required' => 'Describe el procedimiento y los hallazgos.',
            'occurred_on.before_or_equal' => 'La fecha del diagnóstico no puede ser posterior a hoy.',
            'appointment_id.exists' => 'La cita seleccionada no es válida para este paciente.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'doctor_member_id' => 'doctor responsable',
            'occurred_on' => 'fecha',
            'appointment_id' => 'cita',
            'treatment_id' => 'tratamiento',
            'tooth_fdi' => 'pieza dental',
            'objective' => 'nota',
            'subjective' => 'subjetivo',
            'analysis' => 'análisis',
            'plan' => 'plan',
        ];
    }
}
