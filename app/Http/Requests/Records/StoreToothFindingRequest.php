<?php

namespace App\Http\Requests\Records;

use App\Enums\Dentition;
use App\Enums\ToothCondition;
use App\Enums\ToothSurface;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF 6.3 Actualizar odontograma: pieza FDI, estado y caras afectadas. */
class StoreToothFindingRequest extends FormRequest
{
    protected $errorBag = 'tooth';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tooth_fdi' => [
                'required', 'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Dentition::forTooth((int) $value) === null) {
                        $fail('La pieza dental no es válida (numeración FDI: 11-48 o 51-85).');
                    }
                },
            ],
            'condition' => ['required', Rule::enum(ToothCondition::class)],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => [Rule::enum(ToothSurface::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['tooth_fdi' => 'pieza dental', 'condition' => 'estado', 'surfaces' => 'caras'];
    }
}
