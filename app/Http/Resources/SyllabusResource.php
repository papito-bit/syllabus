<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyllabusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');

        return [
            'id' => $this->public_id,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'ec' => EcResource::make($this->whenLoaded('ec')),
            'annee_universitaire' => AnneeUniversitaireResource::make($this->whenLoaded('anneeUniversitaire')),

            'objectifs' => $this->objectifs,
            'prerequis' => $this->prerequis,
            'competences' => $this->competences,
            'resultats_apprentissage' => $this->resultats_apprentissage,
            'programme' => $this->programme,
            'methodes_pedagogiques' => $this->methodes_pedagogiques,
            'evaluation' => $this->evaluation,
            'bibliographie' => $this->bibliographie,
            'sections_supplementaires' => $this->sections_supplementaires ?? [],

            'enseignants' => $this->whenLoaded('enseignants', function () {
                return $this->enseignants->map(fn ($enseignant) => [
                    'nom' => $enseignant->name,
                    'est_responsable' => (bool) $enseignant->pivot->est_responsable,
                ])->values();
            }),

            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'derniere_modification_par' => $this->whenLoaded('modificateur', fn () => $this->modificateur?->name),

            // Indications pour l'interface (affichage des boutons). La vraie vérification
            // reste faite côté serveur à chaque requête de modification.
            'permissions' => [
                'modifier' => $user !== null && $user->can('update', $this->resource),
                'changer_statut' => $user !== null && $user->can('changerStatut', $this->resource),
            ],
        ];
    }
}
