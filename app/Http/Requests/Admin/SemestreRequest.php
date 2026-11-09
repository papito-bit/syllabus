<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SemestreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $semestre = $this->route('semestre');

        return [
            'code' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('semestres', 'code')->ignore($semestre?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'code', 'libelle' => 'libellé', 'ordre' => 'ordre'];
    }
}
