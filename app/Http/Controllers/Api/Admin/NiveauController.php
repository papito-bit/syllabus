<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NiveauRequest;
use App\Http\Resources\NiveauResource;
use App\Models\Niveau;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class NiveauController extends Controller
{
    use EnregistreModifications;

    public function index(): AnonymousResourceCollection
    {
        $niveaux = Niveau::query()
            ->withCount(['parcours', 'maquettes'])
            ->orderBy('ordre')
            ->orderBy('code')
            ->get();

        return NiveauResource::collection($niveaux);
    }

    public function store(NiveauRequest $request, AuditService $audit): JsonResponse
    {
        $niveau = DB::transaction(function () use ($request, $audit) {
            $niveau = Niveau::create($request->validated())->refresh();

            $audit->journaliser(
                $request->user(), 'creation', $niveau, null,
                $niveau->only(['code', 'libelle', 'ordre']),
                "Création d'un niveau"
            );

            return $niveau;
        });

        return (new NiveauResource($niveau))->response()->setStatusCode(201);
    }

    public function update(NiveauRequest $request, Niveau $niveau, AuditService $audit): NiveauResource
    {
        DB::transaction(fn () => $this->appliquerModification(
            $niveau, $request->validated(), $request->user(), $audit, "Modification d'un niveau"
        ));

        return new NiveauResource($niveau->loadCount(['parcours', 'maquettes']));
    }

    public function destroy(Request $request, Niveau $niveau, AuditService $audit): Response
    {
        $this->refuserSiUtilise(
            $niveau->maquettes()->exists(),
            'Ce niveau possède des maquettes : il ne peut pas être supprimé.'
        );

        DB::transaction(function () use ($request, $niveau, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $niveau,
                $niveau->only(['code', 'libelle', 'ordre']), null,
                "Suppression d'un niveau"
            );
            $niveau->delete();
        });

        return response()->noContent();
    }
}
