<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création (POST) : niveau, parcours et année sont obligatoires.
 * Modification (PUT) : seuls le titre et les notes se modifient ; le niveau, le parcours
 * et l'année d'une maquette sont définitifs (pour changer, on duplique).
 */
class MaquetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    public function rules(): array
    {
        $commun = [
            'titre' => ['sometimes', 'nullable', 'string', 'max:190'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];

        if ($this->isMethod('POST')) {
            return $commun + [
                'niveau' => ['required', 'string', 'size:26', Rule::exists('niveaux', 'public_id')],
                'parcours' => ['required', 'string', 'size:26', Rule::exists('parcours', 'public_id')],
                'annee' => ['required', 'string', 'size:26', Rule::exists('annees_universitaires', 'public_id')],
            ];
        }

        return $commun + [
            'niveau' => ['prohibited'],
            'parcours' => ['prohibited'],
            'annee' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'niveau' => 'niveau',
            'parcours' => 'parcours',
            'annee' => 'année universitaire',
            'titre' => 'titre',
            'notes' => 'notes',
        ];
    }
}
