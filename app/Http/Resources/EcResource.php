<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'intitule' => $this->intitule,
            'description' => $this->description,
            'lignes_count' => $this->whenCounted('lignes'),
            'syllabus_count' => $this->whenCounted('syllabus'),
        ];
    }
}
