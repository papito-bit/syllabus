<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnneeUniversitaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'libelle' => $this->libelle,
            'is_courante' => (bool) $this->is_courante,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'maquettes_count' => $this->whenCounted('maquettes'),
            'syllabus_count' => $this->whenCounted('syllabus'),
        ];
    }
}
