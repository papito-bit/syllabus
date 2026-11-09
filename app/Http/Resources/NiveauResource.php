<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'parcours_count' => $this->whenCounted('parcours'),
            'maquettes_count' => $this->whenCounted('maquettes'),
        ];
    }
}
