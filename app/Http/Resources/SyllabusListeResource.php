<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Version allégée d'un syllabus pour les listes (sans le texte du contenu). */
class SyllabusListeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'ec' => EcResource::make($this->whenLoaded('ec')),
            'annee_universitaire' => AnneeUniversitaireResource::make($this->whenLoaded('anneeUniversitaire')),
            'enseignants' => $this->whenLoaded('enseignants', function () {
                return $this->enseignants->map(fn ($enseignant) => [
                    'nom' => $enseignant->name,
                    'est_responsable' => (bool) $enseignant->pivot->est_responsable,
                ])->values();
            }),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
