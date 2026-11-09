<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SemestreRequest;
use App\Http\Resources\SemestreResource;
use App\Models\Semestre;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SemestreController extends Controller
{
    use EnregistreModifications;

    public function index(): AnonymousResourceCollection
    {
        $semestres = Semestre::query()->withCount('unites')->orderBy('ordre')->orderBy('code')->get();

        return SemestreResource::collection($semestres);
    }

    public function store(SemestreRequest $request, AuditService $audit): JsonResponse
    {
        $semestre = DB::transaction(function () use ($request, $audit) {
            $semestre = Semestre::create($request->validated())->refresh();

            $audit->journaliser(
                $request->user(), 'creation', $semestre, null,
                $semestre->only(['code', 'libelle', 'ordre']),
                "Création d'un semestre"
            );

            return $semestre;
        });

        return (new SemestreResource($semestre))->response()->setStatusCode(201);
    }

    public function update(SemestreRequest $request, Semestre $semestre, AuditService $audit): SemestreResource
    {
        DB::transaction(fn () => $this->appliquerModification(
            $semestre, $request->validated(), $request->user(), $audit, "Modification d'un semestre"
        ));

        return new SemestreResource($semestre->loadCount('unites'));
    }

    public function destroy(Request $request, Semestre $semestre, AuditService $audit): Response
    {
        $this->refuserSiUtilise(
            $semestre->unites()->exists(),
            'Ce semestre est utilisé par des unités d\'enseignement : il ne peut pas être supprimé.'
        );

        DB::transaction(function () use ($request, $semestre, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $semestre,
                $semestre->only(['code', 'libelle', 'ordre']), null,
                "Suppression d'un semestre"
            );
            $semestre->delete();
        });

        return response()->noContent();
    }
}
