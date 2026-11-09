<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneMaquetteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'ordre' => $this->ordre,
            'ec' => EcResource::make($this->whenLoaded('ec')),
            'heures_cm' => $this->heures_cm,
            'heures_td' => $this->heures_td,
            'heures_tp' => $this->heures_tp,
            'heures_total' => $this->heures_total,
            'credits' => $this->credits,
            'coefficient' => $this->coefficient,
            'modalite_evaluation' => $this->modalite_evaluation,
            'donnees_supplementaires' => $this->donnees_supplementaires,
            // Identifiant public du syllabus de cet EC (null s'il n'existe pas ou s'il n'est pas visible)
            'syllabus_id' => $this->getAttribute('syllabus_public_id'),
        ];
    }
}
