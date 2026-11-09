<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\EnregistreModifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnneeRequest;
use App\Http\Resources\AnneeUniversitaireResource;
use App\Models\AnneeUniversitaire;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AnneeUniversitaireController extends Controller
{
    use EnregistreModifications;

    public function index(): AnonymousResourceCollection
    {
        $annees = AnneeUniversitaire::query()
            ->withCount(['maquettes', 'syllabus'])
            ->orderByDesc('libelle')
            ->get();

        return AnneeUniversitaireResource::collection($annees);
    }

    public function store(AnneeRequest $request, AuditService $audit): JsonResponse
    {
        $annee = DB::transaction(function () use ($request, $audit) {
            $donnees = $request->validated();

            // Une seule année courante à la fois
            if (! empty($donnees['is_courante'])) {
                AnneeUniversitaire::where('is_courante', true)->update(['is_courante' => false]);
            }

            $annee = AnneeUniversitaire::create($donnees)->refresh();

            $audit->journaliser(
                $request->user(), 'creation', $annee, null,
                $annee->only(['libelle', 'is_courante']),
                "Création d'une année universitaire"
            );

            return $annee;
        });

        return (new AnneeUniversitaireResource($annee))->response()->setStatusCode(201);
    }

    public function update(AnneeRequest $request, AnneeUniversitaire $annee, AuditService $audit): AnneeUniversitaireResource
    {
        DB::transaction(function () use ($request, $annee, $audit) {
            $donnees = $request->validated();

            if (! empty($donnees['is_courante'])) {
                AnneeUniversitaire::where('is_courante', true)
                    ->where('id', '!=', $annee->id)
                    ->update(['is_courante' => false]);
            }

            $this->appliquerModification(
                $annee, $donnees, $request->user(), $audit, "Modification d'une année universitaire"
            );
        });

        return new AnneeUniversitaireResource($annee->loadCount(['maquettes', 'syllabus']));
    }

    public function destroy(Request $request, AnneeUniversitaire $annee, AuditService $audit): Response
    {
        $this->refuserSiUtilise(
            $annee->maquettes()->exists() || $annee->syllabus()->exists(),
            'Cette année possède des maquettes ou des syllabus : elle ne peut pas être supprimée.'
        );

        DB::transaction(function () use ($request, $annee, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $annee,
                $annee->only(['libelle', 'is_courante']), null,
                "Suppression d'une année universitaire"
            );
            $annee->delete();
        });

        return response()->noContent();
    }
}
