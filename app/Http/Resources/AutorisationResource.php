<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutorisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->public_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'est_responsable' => (bool) $this->est_responsable,
            'accorde_le' => $this->accorde_le?->toIso8601String(),
            'accorde_par' => $this->accordePar?->name,
        ];
    }
}
