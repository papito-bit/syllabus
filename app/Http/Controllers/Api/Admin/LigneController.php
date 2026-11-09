<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ModifieUneMaquette;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LigneRequest;
use App\Http\Resources\LigneMaquetteResource;
use App\Models\Ec;
use App\Models\LigneMaquette;
use App\Models\UniteEnseignement;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Lignes de maquette (un EC dans une UE) d'une maquette en brouillon. Journalisé sur la maquette. */
class LigneController extends Controller
{
    use ModifieUneMaquette;

    private const CHAMPS = [
        'ordre', 'heures_cm', 'heures_td', 'heures_tp', 'heures_total',
        'credits', 'coefficient', 'modalite_evaluation', 'donnees_supplementaires',
    ];

    public function store(LigneRequest $request, UniteEnseignement $unite, AuditService $audit): JsonResponse
    {
        $maquette = $unite->maquette;
        $this->exigerBrouillon($maquette);

        $donnees = $request->validated();
        $ec = Ec::where('public_id', $donnees['ec'])->firstOrFail();

        if (LigneMaquette::where('ue_id', $unite->id)->where('ec_id', $ec->id)->exists()) {
            throw ValidationException::withMessages(['ec' => ['Cet EC figure déjà dans cette UE.']]);
        }
        $this->verifierColonnes($maquette, $donnees['donnees_supplementaires'] ?? null);

        $ligne = DB::transaction(function () use ($request, $maquette, $unite, $ec, $donnees, $audit) {
            $attributs = $this->attributs($donnees, null);

            $ligne = LigneMaquette::create(['ue_id' => $unite->id, 'ec_id' => $ec->id] + $attributs)->refresh();

            $audit->journaliser(
                $request->user(), 'ajout_ec', $maquette, null,
                ['ue' => $unite->code, 'ec' => $ec->code, 'heures_total' => $ligne->heures_total],
                "Ajout d'un EC à une UE"
            );

            return $ligne;
        });

        return (new LigneMaquetteResource($ligne->load('ec')))->response()->setStatusCode(201);
    }

    public function update(LigneRequest $request, LigneMaquette $ligne, AuditService $audit): LigneMaquetteResource
    {
        $unite = $ligne->ue;
        $maquette = $unite->maquette;
        $this->exigerBrouillon($maquette);

        $donnees = $request->validated();
        $this->verifierColonnes($maquette, $donnees['donnees_supplementaires'] ?? null);

        DB::transaction(function () use ($request, $maquette, $unite, $ligne, $donnees, $audit) {
            $ligne->fill($this->attributs($donnees, $ligne));

            $champs = array_keys($ligne->getDirty());
            if ($champs === []) {
                return;
            }

            $code = $ligne->ec->code;
            $anciennes = ['ue' => $unite->code, 'ec' => $code] + Arr::only($ligne->getOriginal(), $champs);
            $nouvelles = ['ue' => $unite->code, 'ec' => $code] + $ligne->only($champs);
            $ligne->save();

            $audit->journaliser($request->user(), 'modification_ec', $maquette, $anciennes, $nouvelles, "Modification d'un EC dans une UE");
        });

        return new LigneMaquetteResource($ligne->refresh()->load('ec'));
    }

    public function destroy(Request $request, LigneMaquette $ligne, AuditService $audit): Response
    {
        $unite = $ligne->ue;
        $maquette = $unite->maquette;
        $this->exigerBrouillon($maquette);

        DB::transaction(function () use ($request, $maquette, $unite, $ligne, $audit) {
            $audit->journaliser(
                $request->user(), 'retrait_ec', $maquette,
                ['ue' => $unite->code, 'ec' => $ligne->ec->code], null,
                "Retrait d'un EC d'une UE"
            );
            $ligne->delete();
        });

        return response()->noContent();
    }

    /**
     * Champs modifiables, avec calcul du volume horaire total (CM + TD + TP) quand il n'est pas fourni
     * et que les heures de CM, TD ou TP sont renseignées.
     */
    private function attributs(array $donnees, ?LigneMaquette $existante): array
    {
        $attributs = Arr::only($donnees, self::CHAMPS);

        $heuresTouchees = array_intersect(['heures_cm', 'heures_td', 'heures_tp'], array_keys($attributs)) !== [];
        if ($heuresTouchees && ! array_key_exists('heures_total', $attributs)) {
            $somme = 0.0;
            foreach (['heures_cm', 'heures_td', 'heures_tp'] as $champ) {
                $valeur = array_key_exists($champ, $attributs) ? $attributs[$champ] : $existante?->{$champ};
                $somme += (float) $valeur;
            }
            $attributs['heures_total'] = $somme;
        }

        return $attributs;
    }
}
