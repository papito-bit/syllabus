<?php

namespace App\Http\Requests;

use App\Enums\Statut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le droit de modifier est vérifié ICI (avant la validation) via la Policy :
 * un enseignant non autorisé reçoit 403, quelles que soient les données envoyées.
 */
class SyllabusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $syllabus = $this->route('syllabus');

        return $this->user() !== null && $this->user()->can('update', $syllabus);
    }

    public function rules(): array
    {
        // Les champs TEXT de MySQL ont une limite de 65 535 octets : 15 000 caractères restent sûrs.
        $texte = ['sometimes', 'nullable', 'string', 'max:15000'];

        return [
            'objectifs' => $texte,
            'prerequis' => $texte,
            'competences' => $texte,
            'resultats_apprentissage' => $texte,
            'methodes_pedagogiques' => $texte,
            'evaluation' => $texte,
            'programme' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'bibliographie' => ['sometimes', 'nullable', 'string', 'max:100000'],

            'sections_supplementaires' => ['sometimes', 'nullable', 'array', 'max:30'],
            'sections_supplementaires.*.titre' => ['required', 'string', 'max:150'],
            'sections_supplementaires.*.contenu' => ['required', 'string', 'max:50000'],

            // Seul un administrateur peut publier, archiver ou remettre en brouillon
            'statut' => $this->user()?->estAdmin()
                ? ['sometimes', Rule::enum(Statut::class)]
                : ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'objectifs' => 'objectifs pédagogiques',
            'prerequis' => 'prérequis',
            'competences' => 'compétences visées',
            'resultats_apprentissage' => "résultats d'apprentissage",
            'methodes_pedagogiques' => 'méthodes pédagogiques',
            'evaluation' => "modalités d'évaluation",
            'programme' => 'programme détaillé',
            'bibliographie' => 'bibliographie',
            'sections_supplementaires' => 'sections supplémentaires',
            'sections_supplementaires.*.titre' => 'titre de la section',
            'sections_supplementaires.*.contenu' => 'contenu de la section',
            'statut' => 'statut',
        ];
    }
}
