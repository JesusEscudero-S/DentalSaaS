<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/** RF 6.6 Registrar aplicación de flúor. La fecha no puede ser posterior a hoy. */
class StoreFluorideApplicationRequest extends FormRequest
{
    protected $errorBag = 'fluoride';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'applied_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:2000-01-01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['applied_on.before_or_equal' => 'La fecha de aplicación no puede ser posterior a hoy.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['applied_on' => 'fecha de aplicación', 'notes' => 'observaciones'];
    }
}
