<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ParcoursRequest;
use App\Http\Resources\ParcoursResource;
use App\Models\Maquette;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcoursController extends Controller
{
    use EnregistreModifications;

    public function index(): AnonymousResourceCollection
    {
        $parcours = Parcours::query()
            ->with('niveaux')
            ->withCount('maquettes')
            ->orderBy('nom')
            ->get();

        return ParcoursResource::collection($parcours);
    }

    public function store(ParcoursRequest $request, AuditService $audit): JsonResponse
    {
        $parcours = DB::transaction(function () use ($request, $audit) {
            $donnees = $request->validated();

            $parcours = Parcours::create(Arr::except($donnees, ['niveaux']))->refresh();
            $idsNiveaux = Niveau::whereIn('public_id', $donnees['niveaux'])->pluck('id');
            $parcours->niveaux()->sync($idsNiveaux);

            $audit->journaliser(
                $request->user(), 'creation', $parcours, null,
                $parcours->only(['code', 'nom', 'is_active']) + ['niveaux' => $this->codesNiveaux($idsNiveaux)],
                "Création d'un parcours"
            );

            return $parcours;
        });

        return (new ParcoursResource($parcours->load('niveaux')->loadCount('maquettes')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ParcoursRequest $request, Parcours $parcours, AuditService $audit): ParcoursResource
    {
        DB::transaction(function () use ($request, $parcours, $audit) {
            $donnees = $request->validated();

            $this->appliquerModification(
                $parcours, Arr::except($donnees, ['niveaux']), $request->user(), $audit, "Modification d'un parcours"
            );

            if (isset($donnees['niveaux'])) {
                $this->synchroniserNiveaux($request, $parcours, $donnees['niveaux'], $audit);
            }
        });

        return new ParcoursResource($parcours->load('niveaux')->loadCount('maquettes'));
    }

    public function destroy(Request $request, Parcours $parcours, AuditService $audit): Response
    {
        $this->refuserSiUtilise(
            $parcours->maquettes()->exists(),
            'Ce parcours possède des maquettes : il ne peut pas être supprimé.'
        );

        DB::transaction(function () use ($request, $parcours, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $parcours,
                $parcours->only(['code', 'nom', 'is_active']), null,
                "Suppression d'un parcours"
            );
            $parcours->delete();
        });

        return response()->noContent();
    }

    /** Remplace les niveaux du parcours, sans retirer un niveau pour lequel une maquette existe. */
    private function synchroniserNiveaux(ParcoursRequest $request, Parcours $parcours, array $idsPublics, AuditService $audit): void
    {
        $nouveaux = Niveau::whereIn('public_id', $idsPublics)->pluck('id');
        $actuels = $parcours->niveaux()->pluck('niveaux.id');
        $retires = $actuels->diff($nouveaux);

        if ($retires->isNotEmpty()
            && Maquette::where('parcours_id', $parcours->id)->whereIn('niveau_id', $retires)->exists()) {
            throw ValidationException::withMessages([
                'niveaux' => ['Ce parcours possède des maquettes pour un niveau que vous retirez.'],
            ]);
        }

        $resultat = $parcours->niveaux()->sync($nouveaux);

        if ($resultat['attached'] !== [] || $resultat['detached'] !== []) {
            $audit->journaliser(
                $request->user(), 'modification', $parcours,
                ['niveaux' => $this->codesNiveaux($actuels)],
                ['niveaux' => $this->codesNiveaux($nouveaux)],
                "Modification des niveaux d'un parcours"
            );
        }
    }

    private function codesNiveaux($ids): array
    {
        return Niveau::whereIn('id', $ids)->orderBy('ordre')->pluck('code')->all();
    }
}
