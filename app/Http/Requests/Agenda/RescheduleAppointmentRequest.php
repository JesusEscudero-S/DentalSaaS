<?php

namespace App\Http\Requests\Agenda;

/**
 * RF 4.3 Reprogramar cita: nueva fecha, hora, duración y (opcional) otro doctor.
 *
 * Hereda de StoreAppointmentRequest: reutiliza las mismas reglas de doctor, fecha, hora y
 * duración, y su withValidator() (no en el pasado, dentro de 8:00–20:00). Solo cambia
 * el error bag y quita los campos que no se editan al reagendar (paciente y motivo).
 */
class RescheduleAppointmentRequest extends StoreAppointmentRequest
{
    protected $errorBag = 'rescheduleAppointment';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['patient_id'], $rules['notes']);

        return $rules;
    }
}
