<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Statut;
use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Api\Admin\Concerns\ModifieUneMaquette;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaquetteRequest;
use App\Http\Resources\MaquetteResource;
use App\Models\AnneeUniversitaire;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\MaquetteColonne;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Models\UniteEnseignement;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'une maquette : brouillon → publiée → archivée.
 * La consultation (liste et détail) se fait par les routes /api/maquettes, qui montrent
 * tous les statuts à un administrateur.
 */
class MaquetteController extends Controller
{
    use EnregistreModifications, ModifieUneMaquette;

    public function store(MaquetteRequest $request, AuditService $audit): JsonResponse
    {
        $donnees = $request->validated();
        $niveau = Niveau::where('public_id', $donnees['niveau'])->firstOrFail();
        $parcours = Parcours::where('public_id', $donnees['parcours'])->firstOrFail();
        $annee = AnneeUniversitaire::where('public_id', $donnees['annee'])->firstOrFail();

        if (! $parcours->is_active) {
            throw ValidationException::withMessages(['parcours' => ['Ce parcours est désactivé.']]);
        }
        if (! $niveau->parcours()->whereKey($parcours->getKey())->exists()) {
            throw ValidationException::withMessages(['parcours' => ["Ce parcours n'est pas proposé pour ce niveau."]]);
        }

        try {
            $maquette = DB::transaction(function () use ($request, $donnees, $niveau, $parcours, $annee, $audit) {
                $maquette = Maquette::create([
                    'niveau_id' => $niveau->id,
                    'parcours_id' => $parcours->id,
                    'annee_universitaire_id' => $annee->id,
                    'version' => $this->prochaineVersion($niveau->id, $parcours->id, $annee->id),
                    'titre' => $donnees['titre'] ?? null,
                    'notes' => $donnees['notes'] ?? null,
                    'statut' => Statut::Brouillon,
                    'created_by' => $request->user()->id,
                ])->refresh();

                $audit->journaliser(
                    $request->user(), 'creation', $maquette, null,
                    ['niveau' => $niveau->code, 'parcours' => $parcours->code, 'annee' => $annee->libelle, 'version' => $maquette->version],
                    "Création d'une maquette"
                );

                return $maquette;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, "Une autre version vient d'être créée en même temps. Réessayez.");
        }

        return (new MaquetteResource($this->chargerMaquette($maquette)))->response()->setStatusCode(201);
    }

    /** Titre et notes uniquement, et seulement tant que la maquette est un brouillon. */
    public function update(MaquetteRequest $request, Maquette $maquette, AuditService $audit): MaquetteResource
    {
        $this->exigerBrouillon($maquette);

        DB::transaction(fn () => $this->appliquerModification(
            $maquette, Arr::only($request->validated(), ['titre', 'notes']), $request->user(), $audit, "Modification d'une maquette"
        ));

        return new MaquetteResource($this->chargerMaquette($maquette));
    }

    /**
     * Publication. Une maquette vide ou incomplète est refusée, et une version déjà publiée
     * pour le même niveau, parcours et année n'est JAMAIS remplacée sans confirmation explicite
     * (confirmer_remplacement = true) : elle est alors archivée, pas supprimée.
     */
    public function publier(Request $request, Maquette $maquette, AuditService $audit): MaquetteResource|JsonResponse
    {
        $request->validate(
            ['confirmer_remplacement' => ['sometimes', 'boolean']],
            [],
            ['confirmer_remplacement' => 'confirmation du remplacement']
        );
        $user = $request->user();

        if ($maquette->statut !== Statut::Brouillon) {
            abort(409, 'Seule une maquette en brouillon peut être publiée.');
        }
        Gate::forUser($user)->authorize('publish', $maquette);

        $this->verifierPubliable($maquette);

        $publiee = Maquette::query()
            ->publiees()
            ->where('niveau_id', $maquette->niveau_id)
            ->where('parcours_id', $maquette->parcours_id)
            ->where('annee_universitaire_id', $maquette->annee_universitaire_id)
            ->where('id', '!=', $maquette->id)
            ->first();

        if ($publiee !== null && ! $request->boolean('confirmer_remplacement')) {
            return response()->json([
                'message' => "Une version publiée (v{$publiee->version}) existe déjà pour ce parcours et cette année. "
                    .'Confirmez le remplacement : elle sera archivée.',
                'version_publiee' => ['id' => $publiee->public_id, 'version' => $publiee->version],
            ], 409);
        }

        DB::transaction(function () use ($maquette, $publiee, $user, $audit) {
            if ($publiee !== null) {
                $publiee->update(['statut' => Statut::Archive]);
                $audit->journaliser(
                    $user, 'archivage', $publiee,
                    ['statut' => 'publie'], ['statut' => 'archive'],
                    "Archivée automatiquement : remplacée par la version {$maquette->version}"
                );
            }

            $maquette->update([
                'statut' => Statut::Publie,
                'published_at' => now(),
                'published_by' => $user->id,
            ]);

            $audit->journaliser(
                $user, 'publication', $maquette,
                ['statut' => 'brouillon'], ['statut' => 'publie'],
                "Publication d'une maquette"
            );
        });

        return new MaquetteResource($this->chargerMaquette($maquette->refresh()));
    }

    public function archiver(Request $request, Maquette $maquette, AuditService $audit): MaquetteResource
    {
        if ($maquette->statut !== Statut::Publie) {
            abort(409, 'Seule une maquette publiée peut être archivée.');
        }
        Gate::forUser($request->user())->authorize('archive', $maquette);

        DB::transaction(function () use ($request, $maquette, $audit) {
            $maquette->update(['statut' => Statut::Archive]);
            $audit->journaliser(
                $request->user(), 'archivage', $maquette,
                ['statut' => 'publie'], ['statut' => 'archive'],
                "Archivage d'une maquette"
            );
        });

        return new MaquetteResource($this->chargerMaquette($maquette->refresh()));
    }

    /** Suppression réservée aux brouillons : une maquette publiée ne se supprime pas, elle s'archive. */
    public function destroy(Request $request, Maquette $maquette, AuditService $audit): Response
    {
        if ($maquette->statut !== Statut::Brouillon) {
            abort(409, "Seule une maquette en brouillon peut être supprimée : une maquette publiée s'archive.");
        }
        Gate::forUser($request->user())->authorize('delete', $maquette);

        DB::transaction(function () use ($request, $maquette, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $maquette,
                [
                    'version' => $maquette->version,
                    'unites' => $maquette->unites()->count(),
                    'lignes' => LigneMaquette::whereIn('ue_id', $maquette->unites()->pluck('id'))->count(),
                ],
                null,
                "Suppression d'un brouillon de maquette"
            );
            $maquette->delete(); // les UE, lignes et colonnes sont supprimées en cascade
        });

        return response()->noContent();
    }

    /**
     * Copie la structure complète (colonnes, UE, EC) vers une nouvelle maquette en BROUILLON,
     * pour une autre année ou comme nouvelle version de la même année. Les syllabus ne sont pas copiés.
     */
    public function dupliquer(Request $request, Maquette $maquette, AuditService $audit): JsonResponse
    {
        $donnees = $request->validate(
            [
                'annee' => ['required', 'string', 'size:26', 'exists:annees_universitaires,public_id'],
                'titre' => ['sometimes', 'nullable', 'string', 'max:190'],
            ],
            [],
            ['annee' => 'année universitaire', 'titre' => 'titre']
        );
        $user = $request->user();
        $annee = AnneeUniversitaire::where('public_id', $donnees['annee'])->firstOrFail();

        try {
            $copie = DB::transaction(function () use ($maquette, $annee, $donnees, $user, $audit) {
                $maquette->load(['colonnes', 'unites.lignes']);

                $copie = Maquette::create([
                    'niveau_id' => $maquette->niveau_id,
                    'parcours_id' => $maquette->parcours_id,
                    'annee_universitaire_id' => $annee->id,
                    'version' => $this->prochaineVersion($maquette->niveau_id, $maquette->parcours_id, $annee->id),
                    'titre' => $donnees['titre'] ?? $maquette->titre,
                    'notes' => $maquette->notes,
                    'statut' => Statut::Brouillon,
                    'created_by' => $user->id,
                ]);

                foreach ($maquette->colonnes as $colonne) {
                    MaquetteColonne::create([
                        'maquette_id' => $copie->id,
                        'cle' => $colonne->cle,
                        'libelle' => $colonne->libelle,
                        'ordre' => $colonne->ordre,
                    ]);
                }

                $nombreLignes = 0;
                foreach ($maquette->unites as $unite) {
                    $nouvelleUnite = UniteEnseignement::create([
                        'maquette_id' => $copie->id,
                        'semestre_id' => $unite->semestre_id,
                        'code' => $unite->code,
                        'intitule' => $unite->intitule,
                        'ordre' => $unite->ordre,
                        'credits' => $unite->credits,
                        'coefficient' => $unite->coefficient,
                        'donnees_supplementaires' => $unite->donnees_supplementaires,
                    ]);

                    foreach ($unite->lignes as $ligne) {
                        LigneMaquette::create([
                            'ue_id' => $nouvelleUnite->id,
                            'ec_id' => $ligne->ec_id,
                            'ordre' => $ligne->ordre,
                            'heures_cm' => $ligne->heures_cm,
                            'heures_td' => $ligne->heures_td,
                            'heures_tp' => $ligne->heures_tp,
                            'heures_total' => $ligne->heures_total,
                            'credits' => $ligne->credits,
                            'coefficient' => $ligne->coefficient,
                            'modalite_evaluation' => $ligne->modalite_evaluation,
                            'donnees_supplementaires' => $ligne->donnees_supplementaires,
                        ]);
                        $nombreLignes++;
                    }
                }

                $audit->journaliser(
                    $user, 'duplication', $copie, null,
                    [
                        'source' => $maquette->public_id,
                        'annee' => $annee->libelle,
                        'version' => $copie->version,
                        'unites' => $maquette->unites->count(),
                        'lignes' => $nombreLignes,
                    ],
                    "Duplication d'une maquette"
                );

                return $copie->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, "Une autre version vient d'être créée en même temps. Réessayez.");
        }

        return (new MaquetteResource($this->chargerMaquette($copie)))->response()->setStatusCode(201);
    }

    /** Refuse la publication d'une maquette vide ou contenant des UE sans aucun EC. */
    private function verifierPubliable(Maquette $maquette): void
    {
        $unites = $maquette->unites()->withCount('lignes')->get();

        if ($unites->isEmpty()) {
            throw ValidationException::withMessages([
                'maquette' => ['Une maquette vide ne peut pas être publiée : ajoutez au moins une UE et un EC.'],
            ]);
        }

        $sansEc = $unites->where('lignes_count', 0)->pluck('code');
        if ($sansEc->isNotEmpty()) {
            throw ValidationException::withMessages([
                'maquette' => ["Ces UE n'ont aucun EC : {$sansEc->implode(', ')}."],
            ]);
        }
    }
}
