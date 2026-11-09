<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ligne de maquette : un EC placé dans une UE, avec ses volumes horaires.
 * À la modification, l'EC ne change pas (on retire la ligne et on en ajoute une autre).
 */
class LigneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    public function rules(): array
    {
        $heures = ['sometimes', 'nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:9999.9'];

        return [
            'ec' => $this->isMethod('POST')
                ? ['required', 'string', 'size:26', Rule::exists('ecs', 'public_id')]
                : ['prohibited'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'heures_cm' => $heures,
            'heures_td' => $heures,
            'heures_tp' => $heures,
            'heures_total' => $heures,
            'credits' => ['sometimes', 'nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:9999.9'],
            'coefficient' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            'modalite_evaluation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'donnees_supplementaires' => ['sometimes', 'nullable', 'array', 'max:15'],
            'donnees_supplementaires.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** Détection d'incohérence : le total doit valoir CM + TD + TP quand les quatre valeurs sont fournies. */
    public function withValidator($validateur): void
    {
        $validateur->after(function ($validateur) {
            $champs = ['heures_cm', 'heures_td', 'heures_tp', 'heures_total'];

            foreach ($champs as $champ) {
                if (! is_numeric($this->input($champ))) {
                    return;
                }
            }

            $somme = (float) $this->input('heures_cm') + (float) $this->input('heures_td') + (float) $this->input('heures_tp');
            if (abs((float) $this->input('heures_total') - $somme) > 0.05) {
                $validateur->errors()->add(
                    'heures_total',
                    'Le volume horaire total doit être égal à CM + TD + TP ('.rtrim(rtrim(number_format($somme, 1, '.', ''), '0'), '.').' h).'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'ec' => 'EC',
            'ordre' => 'ordre',
            'heures_cm' => 'heures de CM',
            'heures_td' => 'heures de TD',
            'heures_tp' => 'heures de TP',
            'heures_total' => 'volume horaire total',
            'credits' => 'crédits',
            'coefficient' => 'coefficient',
            'modalite_evaluation' => "modalité d'évaluation",
            'donnees_supplementaires' => 'colonnes supplémentaires',
            'donnees_supplementaires.*' => 'valeur de colonne supplémentaire',
        ];
    }
}
