<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/** Adenda a una nota firmada: el texto que se agrega y por qué. */
class StoreAddendumRequest extends FormRequest
{
    protected $errorBag = 'addendum';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['content' => 'adenda', 'reason' => 'motivo'];
    }
}
