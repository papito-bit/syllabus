<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UniteEnseignementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'intitule' => $this->intitule,
            'ordre' => $this->ordre,
            'credits' => $this->credits,
            'coefficient' => $this->coefficient,
            'donnees_supplementaires' => $this->donnees_supplementaires,
            'semestre' => SemestreResource::make($this->whenLoaded('semestre')),
            'lignes' => LigneMaquetteResource::collection($this->whenLoaded('lignes')),
        ];
    }
}
