<?php

namespace App\Http\Requests\Records;

/**
 * RF 6.5 Modificar diagnóstico dental: mismas reglas que al crear, pero sin cambiar el tipo,
 * la fecha ni la cita (esos datos son de la consulta, que ya existe).
 */
class UpdateClinicalNoteRequest extends StoreClinicalNoteRequest
{
    protected $errorBag = 'editNote';

    public function isSoap(): bool
    {
        return $this->route('note')->isSoap();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['note_type'], $rules['occurred_on'], $rules['appointment_id']);

        return $rules;
    }
}
