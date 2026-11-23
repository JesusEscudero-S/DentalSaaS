<?php

namespace App\Http\Requests\Finances;

use App\Enums\MovementDirection;
use App\Models\FinancialCategory;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-05: alta y edición de categoría (un error bag por modal). */
class FinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->category() ? 'editCategory' : 'createCategory';

        $name = $this->input('name');
        $this->merge(['name' => is_string($name) ? preg_replace('/\s+/', ' ', trim($name)) : $name]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('financial_categories', 'name')
                    ->where('organization_id', app(CurrentOrganization::class)->id())
                    ->where('direction', (string) $this->input('direction'))
                    ->ignore($this->category()?->id),
            ],
            'direction' => [
                'required', Rule::enum(MovementDirection::class),
                function (string $attribute, mixed $value, Closure $fail) {
                    $category = $this->category();
                    if ($category && $category->direction->value !== $value && $category->movements()->exists()) {
                        $fail('No se puede cambiar el tipo de una categoría que ya tiene movimientos.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Ya existe una categoría con ese nombre'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'direction' => 'tipo'];
    }

    private function category(): ?FinancialCategory
    {
        return $this->route('category');
    }
}
