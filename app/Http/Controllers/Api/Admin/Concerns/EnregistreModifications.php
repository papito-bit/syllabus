<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait EnregistreModifications
{
    /**
     * Applique des modifications à un modèle et ne journalise que ce qui a réellement changé.
     * Rien n'est écrit (ni journalisé) si les valeurs sont identiques.
     */
    protected function appliquerModification(
        Model $modele,
        array $donnees,
        ?User $acteur,
        AuditService $audit,
        string $description,
    ): void {
        $modele->fill($donnees);

        $champs = array_keys($modele->getDirty());
        if ($champs === []) {
            return;
        }

        $anciennes = Arr::only($modele->getOriginal(), $champs);
        $nouvelles = $modele->only($champs);

        $modele->save();

        $audit->journaliser($acteur, 'modification', $modele, $anciennes, $nouvelles, $description);
    }

    /** Refuse (409) la suppression d'un élément encore utilisé : pas de suppression de données officielles. */
    protected function refuserSiUtilise(bool $utilise, string $message): void
    {
        if ($utilise) {
            abort(409, $message);
        }
    }
}
