<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ModifieUneMaquette;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UniteRequest;
use App\Http\Resources\UniteEnseignementResource;
use App\Models\Maquette;
use App\Models\Semestre;
use App\Models\UniteEnseignement;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Unités d'enseignement d'une maquette en brouillon. Les modifications sont journalisées sur la maquette. */
class UniteController extends Controller
{
    use ModifieUneMaquette;

    public function store(UniteRequest $request, Maquette $maquette, AuditService $audit): JsonResponse
    {
        $this->exigerBrouillon($maquette);

        $donnees = $request->validated();
        $semestre = Semestre::where('public_id', $donnees['semestre'])->firstOrFail();

        $this->verifierCodeUnique($maquette, $semestre->id, $donnees['code']);
        $this->verifierColonnes($maquette, $donnees['donnees_supplementaires'] ?? null);

        $unite = DB::transaction(function () use ($request, $maquette, $semestre, $donnees, $audit) {
            $unite = UniteEnseignement::create(
                ['maquette_id' => $maquette->id, 'semestre_id' => $semestre->id]
                + Arr::only($donnees, ['code', 'intitule', 'ordre', 'credits', 'coefficient', 'donnees_supplementaires'])
            )->refresh();

            $audit->journaliser(
                $request->user(), 'ajout_ue', $maquette, null,
                ['ue' => $unite->code, 'intitule' => $unite->intitule, 'semestre' => $semestre->code],
                "Ajout d'une UE"
            );

            return $unite;
        });

        return (new UniteEnseignementResource($unite->load('semestre')))->response()->setStatusCode(201);
    }

    public function update(UniteRequest $request, UniteEnseignement $unite, AuditService $audit): UniteEnseignementResource
    {
        $maquette = $unite->maquette;
        $this->exigerBrouillon($maquette);

        $donnees = $request->validated();
        $semestreId = $unite->semestre_id;
        if (isset($donnees['semestre'])) {
            $semestreId = Semestre::where('public_id', $donnees['semestre'])->firstOrFail()->id;
        }

        $this->verifierCodeUnique($maquette, $semestreId, $donnees['code'], $unite->id);
        $this->verifierColonnes($maquette, $donnees['donnees_supplementaires'] ?? null);

        DB::transaction(function () use ($request, $maquette, $unite, $donnees, $semestreId, $audit) {
            $ancienCode = $unite->code;

            $unite->fill(Arr::only($donnees, ['code', 'intitule', 'ordre', 'credits', 'coefficient', 'donnees_supplementaires']));
            $unite->semestre_id = $semestreId;

            $champs = array_keys($unite->getDirty());
            if ($champs === []) {
                return;
            }

            $anciennes = ['ue' => $ancienCode] + Arr::only($unite->getOriginal(), $champs);
            $nouvelles = ['ue' => $donnees['code']] + $unite->only($champs);
            $unite->save();

            $audit->journaliser($request->user(), 'modification_ue', $maquette, $anciennes, $nouvelles, "Modification d'une UE");
        });

        return new UniteEnseignementResource($unite->refresh()->load('semestre'));
    }

    public function destroy(Request $request, UniteEnseignement $unite, AuditService $audit): Response
    {
        $maquette = $unite->maquette;
        $this->exigerBrouillon($maquette);

        DB::transaction(function () use ($request, $maquette, $unite, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression_ue', $maquette,
                ['ue' => $unite->code, 'intitule' => $unite->intitule, 'lignes' => $unite->lignes()->count()],
                null,
                "Suppression d'une UE"
            );
            $unite->delete(); // ses lignes (EC) sont supprimées en cascade
        });

        return response()->noContent();
    }

    private function verifierCodeUnique(Maquette $maquette, int $semestreId, string $code, ?int $ignorerId = null): void
    {
        $existe = UniteEnseignement::query()
            ->where('maquette_id', $maquette->id)
            ->where('semestre_id', $semestreId)
            ->where('code', $code)
            ->when($ignorerId, fn ($requete) => $requete->where('id', '!=', $ignorerId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'code' => ['Une UE avec ce code existe déjà dans ce semestre de la maquette.'],
            ]);
        }
    }
}
