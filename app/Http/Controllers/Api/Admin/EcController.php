<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EcRequest;
use App\Http\Resources\EcResource;
use App\Models\Ec;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Catalogue des éléments constitutifs (EC). */
class EcController extends Controller
{
    use EnregistreModifications;

    /** Liste paginée, avec recherche : ?q=texte&per_page=25 */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $requete = Ec::query()->withCount(['lignes', 'syllabus'])->orderBy('code');

        if ($request->filled('q')) {
            $terme = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
            $requete->where(function ($condition) use ($terme) {
                $condition->where('code', 'like', $terme)->orWhere('intitule', 'like', $terme);
            });
        }

        return EcResource::collection($requete->paginate($request->integer('per_page', 25)));
    }

    public function store(EcRequest $request, AuditService $audit): JsonResponse
    {
        $ec = DB::transaction(function () use ($request, $audit) {
            $ec = Ec::create($request->validated())->refresh();

            $audit->journaliser(
                $request->user(), 'creation', $ec, null,
                $ec->only(['code', 'intitule']),
                "Création d'un EC"
            );

            return $ec;
        });

        return (new EcResource($ec))->response()->setStatusCode(201);
    }

    public function update(EcRequest $request, Ec $ec, AuditService $audit): EcResource
    {
        DB::transaction(fn () => $this->appliquerModification(
            $ec, $request->validated(), $request->user(), $audit, "Modification d'un EC"
        ));

        return new EcResource($ec->loadCount(['lignes', 'syllabus']));
    }

    public function destroy(Request $request, Ec $ec, AuditService $audit): Response
    {
        $this->refuserSiUtilise(
            $ec->lignes()->exists() || $ec->syllabus()->exists(),
            'Cet EC figure dans des maquettes ou possède un syllabus : il ne peut pas être supprimé.'
        );

        DB::transaction(function () use ($request, $ec, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $ec,
                $ec->only(['code', 'intitule']), null,
                "Suppression d'un EC"
            );
            $ec->delete();
        });

        return response()->noContent();
    }
}
