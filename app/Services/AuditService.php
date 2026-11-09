<?php

namespace App\Services;

use App\Models\HistoriqueModification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Journalise les modifications importantes dans historique_modifications.
 * Ne jamais y mettre de mot de passe ni de secret.
 */
class AuditService
{
    public function journaliser(
        ?User $user,
        string $action,
        Model $entite,
        ?array $anciennesValeurs = null,
        ?array $nouvellesValeurs = null,
        ?string $description = null,
    ): HistoriqueModification {
        return HistoriqueModification::create([
            'user_id' => $user?->id,
            'entite_type' => Str::snake(class_basename($entite)),
            'entite_id' => $entite->getKey(),
            'entite_public_id' => $entite->getAttribute('public_id'),
            'action' => $action,
            'description' => $description,
            'anciennes_valeurs' => $anciennesValeurs,
            'nouvelles_valeurs' => $nouvellesValeurs,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
