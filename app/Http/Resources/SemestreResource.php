<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemestreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'unites_count' => $this->whenCounted('unites'),
        ];
    }
}
