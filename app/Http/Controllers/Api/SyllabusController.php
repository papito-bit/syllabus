<?php

namespace App\Http\Controllers\Api;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Http\Requests\SyllabusUpdateRequest;
use App\Http\Resources\SyllabusResource;
use App\Models\LigneMaquette;
use App\Models\Syllabus;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SyllabusController extends Controller
{
    /** Étape 6 : page détaillée du syllabus d'un EC. */
    public function show(Request $request, Syllabus $syllabus): SyllabusResource
    {
        $user = $request->user('sanctum');

        // Brouillon ou archivé : 404 pour qui n'a pas le droit de le voir
        abort_unless(Gate::forUser($user)->allows('view', $syllabus), 404);

        $syllabus->load(['ec', 'anneeUniversitaire', 'enseignants', 'modificateur']);

        // Où figure cet EC (niveau, parcours, semestre, heures...) : lignes des maquettes
        // de la même année, publiées sauf pour un administrateur
        $lignes = LigneMaquette::query()
            ->where('ec_id', $syllabus->ec_id)
            ->whereHas('ue.maquette', function ($maquette) use ($syllabus, $user) {
                $maquette->where('annee_universitaire_id', $syllabus->annee_universitaire_id);
                if (! $user?->estAdmin()) {
                    $maquette->publiees();
                }
            })
            ->with(['ue.semestre', 'ue.maquette.niveau', 'ue.maquette.parcours'])
            ->get();

        $contexte = $lignes->map(fn ($ligne) => [
            'maquette_id' => $ligne->ue->maquette->public_id,
            'niveau' => [
                'code' => $ligne->ue->maquette->niveau->code,
                'libelle' => $ligne->ue->maquette->niveau->libelle,
            ],
            'parcours' => [
                'id' => $ligne->ue->maquette->parcours->public_id,
                'code' => $ligne->ue->maquette->parcours->code,
                'nom' => $ligne->ue->maquette->parcours->nom,
            ],
            'semestre' => $ligne->ue->semestre->code,
            'ue' => ['code' => $ligne->ue->code, 'intitule' => $ligne->ue->intitule],
            'heures_cm' => $ligne->heures_cm,
            'heures_td' => $ligne->heures_td,
            'heures_tp' => $ligne->heures_tp,
            'heures_total' => $ligne->heures_total,
            'credits' => $ligne->credits,
            'coefficient' => $ligne->coefficient,
            'modalite_evaluation' => $ligne->modalite_evaluation,
        ])->values();

        return (new SyllabusResource($syllabus))->additional(['contexte' => $contexte]);
    }

    /**
     * Modification d'un syllabus.
     * Le droit est vérifié dans SyllabusUpdateRequest::authorize() (Policy "update") :
     * changer l'identifiant dans l'URL ne permet pas de modifier le syllabus d'un autre.
     */
    public function update(SyllabusUpdateRequest $request, Syllabus $syllabus, AuditService $audit): SyllabusResource
    {
        $user = $request->user();
        $donnees = $request->validated();

        $nouveauStatut = isset($donnees['statut']) ? Statut::from($donnees['statut']) : null;
        unset($donnees['statut']);

        DB::transaction(function () use ($syllabus, $donnees, $nouveauStatut, $user, $audit) {
            $syllabus->fill($donnees);

            if ($nouveauStatut !== null && $nouveauStatut !== $syllabus->statut) {
                // Défense en profondeur : seul un administrateur peut changer le statut
                Gate::forUser($user)->authorize('changerStatut', $syllabus);

                $syllabus->statut = $nouveauStatut;
                if ($nouveauStatut === Statut::Publie && $syllabus->published_at === null) {
                    $syllabus->published_at = now();
                }
            }

            $champsModifies = array_keys($syllabus->getDirty());
            if ($champsModifies === []) {
                return; // rien n'a changé : pas d'écriture, pas d'entrée d'historique
            }

            $anciennes = Arr::only($syllabus->getOriginal(), $champsModifies);
            $nouvelles = $syllabus->only($champsModifies);

            $syllabus->updated_by = $user->id;
            $syllabus->save();

            $audit->journaliser(
                $user,
                'modification',
                $syllabus,
                $anciennes,
                $nouvelles,
                'Modification du syllabus'
            );
        });

        $syllabus->load(['ec', 'anneeUniversitaire', 'enseignants', 'modificateur']);

        return new SyllabusResource($syllabus);
    }
}
