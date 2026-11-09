<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoriqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'entite_type' => $this->entite_type,
            // Identifiant public de l'élément concerné (jamais l'identifiant interne)
            'entite_id' => $this->entite_public_id,
            'action' => $this->action,
            'description' => $this->description,
            'anciennes_valeurs' => $this->anciennes_valeurs,
            'nouvelles_valeurs' => $this->nouvelles_valeurs,
            'utilisateur' => $this->whenLoaded('user', function () {
                return $this->user
                    ? ['id' => $this->user->public_id, 'name' => $this->user->name]
                    : null;
            }),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
