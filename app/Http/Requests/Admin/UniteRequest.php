<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UniteRequest extends FormRequest
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
        $semestre = ['string', 'size:26', Rule::exists('semestres', 'public_id')];

        return [
            'semestre' => $this->isMethod('POST') ? array_merge(['required'], $semestre) : array_merge(['sometimes'], $semestre),
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_.-]+$/'],
            'intitule' => ['required', 'string', 'max:255'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'credits' => ['sometimes', 'nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:9999.9'],
            'coefficient' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            // Valeurs des colonnes supplémentaires de la maquette (clés vérifiées dans le contrôleur)
            'donnees_supplementaires' => ['sometimes', 'nullable', 'array', 'max:15'],
            'donnees_supplementaires.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'semestre' => 'semestre',
            'code' => 'code',
            'intitule' => 'intitulé',
            'ordre' => 'ordre',
            'credits' => 'crédits',
            'coefficient' => 'coefficient',
            'donnees_supplementaires' => 'colonnes supplémentaires',
            'donnees_supplementaires.*' => 'valeur de colonne supplémentaire',
        ];
    }
}
