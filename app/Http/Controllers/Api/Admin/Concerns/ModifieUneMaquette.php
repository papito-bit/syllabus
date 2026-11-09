<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\Maquette;
use Illuminate\Validation\ValidationException;

trait ModifieUneMaquette
{
    /** Une maquette publiée ou archivée est en lecture seule : on ne modifie que les brouillons. */
    protected function exigerBrouillon(Maquette $maquette): void
    {
        if (! $maquette->estModifiable()) {
            abort(409, 'Cette maquette est publiée ou archivée : elle est en lecture seule. Dupliquez-la pour créer une nouvelle version.');
        }
    }

    /** Les valeurs de colonnes supplémentaires ne peuvent utiliser que les colonnes déclarées pour la maquette. */
    protected function verifierColonnes(Maquette $maquette, ?array $donnees, string $champ = 'donnees_supplementaires'): void
    {
        if (empty($donnees)) {
            return;
        }

        $declarees = $maquette->colonnes()->pluck('cle')->all();
        $inconnues = array_diff(array_keys($donnees), $declarees);

        if ($inconnues !== []) {
            throw ValidationException::withMessages([
                $champ => ['Colonne inconnue pour cette maquette : '.implode(', ', $inconnues).'.'],
            ]);
        }
    }

    protected function chargerMaquette(Maquette $maquette): Maquette
    {
        return $maquette->load([
            'niveau', 'parcours', 'anneeUniversitaire', 'colonnes',
            'unites.semestre', 'unites.lignes.ec',
        ]);
    }

    protected function prochaineVersion(int $niveauId, int $parcoursId, int $anneeId): int
    {
        return ((int) Maquette::query()
            ->where('niveau_id', $niveauId)
            ->where('parcours_id', $parcoursId)
            ->where('annee_universitaire_id', $anneeId)
            ->max('version')) + 1;
    }
}
