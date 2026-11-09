<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ParcoursRequest extends FormRequest
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
        $parcours = $this->route('parcours');
        $creation = $this->isMethod('POST');

        return [
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('parcours', 'code')->ignore($parcours?->id),
            ],
            'nom' => ['required', 'string', 'max:190'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            // Identifiants publics des niveaux auxquels le parcours est rattaché
            'niveaux' => $creation ? ['required', 'array', 'min:1'] : ['sometimes', 'array', 'min:1'],
            'niveaux.*' => ['string', 'size:26', 'distinct', Rule::exists('niveaux', 'public_id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'code',
            'nom' => 'nom',
            'description' => 'description',
            'is_active' => 'état actif',
            'niveaux' => 'niveaux',
            'niveaux.*' => 'niveau',
        ];
    }
}
