<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcoursResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'nom' => $this->nom,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'niveaux' => NiveauResource::collection($this->whenLoaded('niveaux')),
            'maquettes_count' => $this->whenCounted('maquettes'),
            'maquettes_publiees_count' => $this->whenCounted('maquettes_publiees'),
        ];
    }
}
