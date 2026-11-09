<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Remplace la liste complète des colonnes supplémentaires d'une maquette. */
class ColonnesRequest extends FormRequest
{
    /** Noms déjà utilisés par les colonnes standard : interdits comme clés personnalisées. */
    private const CLES_RESERVEES = [
        'code', 'intitule', 'ordre', 'semestre', 'ec', 'ue',
        'heures_cm', 'heures_td', 'heures_tp', 'heures_total',
        'credits', 'coefficient', 'modalite_evaluation',
    ];

    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'colonnes' => ['present', 'array', 'max:15'],
            'colonnes.*.cle' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'colonnes.*.libelle' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator($validateur): void
    {
        $validateur->after(function ($validateur) {
            foreach ((array) $this->input('colonnes', []) as $index => $colonne) {
                $cle = is_array($colonne) ? ($colonne['cle'] ?? null) : null;
                if (is_string($cle) && in_array($cle, self::CLES_RESERVEES, true)) {
                    $validateur->errors()->add("colonnes.{$index}.cle", "La clé « {$cle} » est réservée à une colonne standard.");
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'colonnes' => 'colonnes',
            'colonnes.*.cle' => 'clé de la colonne',
            'colonnes.*.libelle' => 'titre de la colonne',
        ];
    }
}
