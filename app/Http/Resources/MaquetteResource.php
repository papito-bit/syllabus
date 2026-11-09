<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaquetteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'titre' => $this->titre,
            'version' => $this->version,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'published_at' => $this->published_at?->toIso8601String(),
            'niveau' => NiveauResource::make($this->whenLoaded('niveau')),
            'parcours' => ParcoursResource::make($this->whenLoaded('parcours')),
            'annee_universitaire' => AnneeUniversitaireResource::make($this->whenLoaded('anneeUniversitaire')),

            // Colonnes supplémentaires propres à cette maquette
            'colonnes' => $this->whenLoaded('colonnes', function () {
                return $this->colonnes->map(fn ($colonne) => [
                    'cle' => $colonne->cle,
                    'libelle' => $colonne->libelle,
                    'ordre' => $colonne->ordre,
                ])->values();
            }),

            // UE regroupées par semestre, dans l'ordre des semestres
            'semestres' => $this->whenLoaded('unites', function () {
                return $this->unites
                    ->groupBy('semestre_id')
                    ->map(fn ($unites) => [
                        'semestre' => SemestreResource::make($unites->first()->semestre),
                        'unites' => UniteEnseignementResource::collection($unites->values()),
                    ])
                    ->sortBy(fn ($groupe) => $groupe['semestre']->resource->ordre)
                    ->values();
            }),
        ];
    }
}
