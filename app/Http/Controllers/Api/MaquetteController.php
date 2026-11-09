<?php

namespace App\Http\Controllers\Api;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Http\Resources\MaquetteResource;
use App\Models\AnneeUniversitaire;
use App\Models\Maquette;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaquetteController extends Controller
{
    /**
     * Liste paginée des maquettes. Un visiteur ne voit que les maquettes publiées ;
     * un administrateur peut aussi filtrer par statut.
     * Filtres : ?niveau=<id>&parcours=<id>&annee=<id>&statut=...
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'niveau' => ['nullable', 'string', 'size:26'],
            'parcours' => ['nullable', 'string', 'size:26'],
            'annee' => ['nullable', 'string', 'size:26'],
            'statut' => ['nullable', Rule::enum(Statut::class)],
        ]);

        $user = $request->user('sanctum');
        $requete = Maquette::query()->with(['niveau', 'parcours', 'anneeUniversitaire']);

        if ($user?->estAdmin()) {
            if ($request->filled('statut')) {
                $requete->where('statut', $request->string('statut')->toString());
            }
        } else {
            $requete->publiees();
        }

        if ($request->filled('niveau')) {
            $requete->whereHas('niveau', fn ($q) => $q->where('public_id', $request->string('niveau')->toString()));
        }
        if ($request->filled('parcours')) {
            $requete->whereHas('parcours', fn ($q) => $q->where('public_id', $request->string('parcours')->toString()));
        }
        if ($request->filled('annee')) {
            $requete->whereHas('anneeUniversitaire', fn ($q) => $q->where('public_id', $request->string('annee')->toString()));
        }

        // Année la plus récente d'abord, puis version la plus haute
        $requete
            ->orderByDesc(
                AnneeUniversitaire::query()
                    ->select('libelle')
                    ->whereColumn('annees_universitaires.id', 'maquettes.annee_universitaire_id')
            )
            ->orderByDesc('version');

        return MaquetteResource::collection($requete->paginate(20));
    }

    /**
     * Étape 5 : la maquette à afficher pour un niveau et un parcours.
     * On prend la maquette publiée de l'année courante si elle existe, sinon la plus récente.
     */
    public function courante(Request $request, Niveau $niveau, Parcours $parcours): MaquetteResource
    {
        // Le parcours doit réellement être associé à ce niveau
        abort_unless($niveau->parcours()->whereKey($parcours->getKey())->exists(), 404);

        $maquette = Maquette::query()
            ->publiees()
            ->with('anneeUniversitaire')
            ->where('niveau_id', $niveau->id)
            ->where('parcours_id', $parcours->id)
            ->get()
            ->sortByDesc(fn ($m) => [
                (int) $m->anneeUniversitaire->is_courante,
                $m->anneeUniversitaire->libelle,
                $m->version,
            ])
            ->first();

        abort_if($maquette === null, 404);

        return $this->show($request, $maquette);
    }

    /** Maquette complète : UE regroupées par semestre, EC, heures, crédits, lien vers les syllabus. */
    public function show(Request $request, Maquette $maquette): MaquetteResource
    {
        $user = $request->user('sanctum');

        // Une maquette non publiée est invisible (404) pour le public : on ne révèle pas son existence
        abort_unless(Gate::forUser($user)->allows('view', $maquette), 404);

        $maquette->load([
            'niveau', 'parcours', 'anneeUniversitaire', 'colonnes',
            'unites.semestre', 'unites.lignes.ec',
        ]);

        // Syllabus de l'année de cette maquette, limités à ceux que l'utilisateur peut voir
        $idsEc = $maquette->unites
            ->flatMap(fn ($unite) => $unite->lignes->pluck('ec_id'))
            ->unique()
            ->all();

        $syllabus = Syllabus::query()
            ->visiblesPour($user)
            ->where('annee_universitaire_id', $maquette->annee_universitaire_id)
            ->whereIn('ec_id', $idsEc)
            ->get(['id', 'public_id', 'ec_id'])
            ->keyBy('ec_id');

        foreach ($maquette->unites as $unite) {
            foreach ($unite->lignes as $ligne) {
                $ligne->setAttribute('syllabus_public_id', $syllabus->get($ligne->ec_id)?->public_id);
            }
        }

        return new MaquetteResource($maquette);
    }
}
