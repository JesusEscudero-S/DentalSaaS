<?php

namespace App\Http\Requests\Agenda;

use App\Models\OrganizationMember;
use App\Services\Agenda\AgendaService;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF 4.1 Agendar nueva cita (modal "Nueva cita" de Figma).
 */
class StoreAppointmentRequest extends FormRequest
{
    /** Error bag propio: los errores vuelven a este modal y no a otro. */
    protected $errorBag = 'createAppointment';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Debe existir, ser de esta clínica y estar activo.
            'patient_id' => [
                'required', 'uuid',
                Rule::exists('patients', 'id')
                    ->where('organization_id', app(CurrentOrganization::class)->id())
                    ->where('status', 'active'),
            ],
            // Debe ser un doctor activo de esta clínica (mismo truco que PatientRequest).
            'doctor_member_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                        $fail('El doctor seleccionado no es válido.');
                    }
                },
            ],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'notes' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'No se pueden agendar citas en fechas pasadas.',
            'duration_minutes.in' => 'Selecciona una duración válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'doctor_member_id' => 'doctor',
            'date' => 'fecha',
            'start_time' => 'hora de inicio',
            'duration_minutes' => 'duración',
            'notes' => 'motivo',
        ];
    }

    /**
     * Reglas que necesitan varios campos a la vez (fecha + hora + duración).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // si ya hay errores, los datos podrían venir incompletos
            }

            $start = Carbon::parse($this->input('date').' '.$this->input('start_time'));
            $end = $start->copy()->addMinutes((int) $this->input('duration_minutes'));

            if ($start->isPast()) {
                $validator->errors()->add('start_time', 'La hora de inicio ya pasó.');

                return;
            }

            // La cita debe caber en el horario del calendario (8:00 a 20:00).
            $opens = $start->copy()->setTime(AgendaService::START_HOUR, 0);
            $closes = $start->copy()->setTime(AgendaService::END_HOUR, 0);

            if ($start->lt($opens) || $end->gt($closes)) {
                $validator->errors()->add('start_time', 'La cita debe quedar entre 8:00 y 20:00.');
            }
        });
    }
}
