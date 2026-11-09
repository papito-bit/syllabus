<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    public function rules(): array
    {
        $annee = $this->route('annee');

        return [
            'libelle' => [
                'required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('annees_universitaires', 'libelle')->ignore($annee?->id),
            ],
            'date_debut' => ['sometimes', 'nullable', 'date'],
            'date_fin' => ['sometimes', 'nullable', 'date', 'after:date_debut'],
            'is_courante' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validateur): void
    {
        $validateur->after(function ($validateur) {
            $libelle = $this->input('libelle');

            if (is_string($libelle)
                && preg_match('/^(\d{4})-(\d{4})$/', $libelle, $annees)
                && (int) $annees[2] !== (int) $annees[1] + 1) {
                $validateur->errors()->add('libelle', "L'année de fin doit suivre l'année de début (exemple : 2026-2027).");
            }
        });
    }

    public function attributes(): array
    {
        return [
            'libelle' => 'année universitaire',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'is_courante' => 'année courante',
        ];
    }
}
