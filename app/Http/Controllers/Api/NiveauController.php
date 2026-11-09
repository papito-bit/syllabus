<?php

namespace App\Http\Controllers\Api;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Http\Resources\NiveauResource;
use App\Http\Resources\ParcoursResource;
use App\Models\Niveau;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NiveauController extends Controller
{
    /** Étape 2 du parcours de consultation : choisir un niveau (L1, L2, M1...). */
    public function index(): AnonymousResourceCollection
    {
        $niveaux = Niveau::query()
            ->withCount(['parcours' => fn ($requete) => $requete->where('parcours.is_active', true)])
            ->orderBy('ordre')
            ->get();

        return NiveauResource::collection($niveaux);
    }

    /**
     * Étape 3 : les parcours réellement associés au niveau choisi.
     * Chaque parcours indique s'il a une maquette publiée POUR CE NIVEAU (maquettes_publiees_count),
     * afin que l'interface n'envoie pas l'utilisateur vers une page vide.
     */
    public function parcours(Niveau $niveau): AnonymousResourceCollection
    {
        $parcours = $niveau->parcours()
            ->where('parcours.is_active', true)
            ->withCount([
                'maquettes as maquettes_publiees_count' => fn ($requete) => $requete
                    ->where('maquettes.niveau_id', $niveau->id)
                    ->where('maquettes.statut', Statut::Publie->value),
            ])
            ->orderBy('parcours.nom')
            ->get();

        return ParcoursResource::collection($parcours);
    }
}
