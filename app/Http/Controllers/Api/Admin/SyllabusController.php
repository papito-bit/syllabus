<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSyllabusRequest;
use App\Http\Resources\SyllabusListeResource;
use App\Http\Resources\SyllabusResource;
use App\Models\AnneeUniversitaire;
use App\Models\Ec;
use App\Models\Syllabus;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des syllabus par l'administrateur : liste, création (en brouillon) et suppression
 * d'un brouillon. La modification du contenu et du statut passe par PUT /api/syllabus/{id}.
 */
class SyllabusController extends Controller
{
    /** Filtres : ?annee=<id>&statut=brouillon&q=texte&sans_enseignant=1&per_page=25 */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'annee' => ['nullable', 'string', 'size:26'],
            'statut' => ['nullable', Rule::enum(Statut::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'sans_enseignant' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $requete = Syllabus::query()
            ->with(['ec', 'anneeUniversitaire', 'enseignants'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($request->filled('annee')) {
            $requete->whereHas('anneeUniversitaire', fn ($q) => $q->where('public_id', $request->string('annee')->toString()));
        }
        if ($request->filled('statut')) {
            $requete->where('statut', $request->string('statut')->toString());
        }
        if ($request->filled('q')) {
            $terme = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
            $requete->whereHas('ec', function ($q) use ($terme) {
                $q->where(function ($condition) use ($terme) {
                    $condition->where('code', 'like', $terme)->orWhere('intitule', 'like', $terme);
                });
            });
        }
        if ($request->boolean('sans_enseignant')) {
            $requete->whereDoesntHave('autorisations', fn ($q) => $q->whereNull('revoque_le'));
        }

        return SyllabusListeResource::collection($requete->paginate($request->integer('per_page', 25)));
    }

    public function store(StoreSyllabusRequest $request, AuditService $audit): JsonResponse
    {
        $ec = Ec::where('public_id', $request->validated('ec'))->firstOrFail();
        $annee = AnneeUniversitaire::where('public_id', $request->validated('annee'))->firstOrFail();

        if (Syllabus::where('ec_id', $ec->id)->where('annee_universitaire_id', $annee->id)->exists()) {
            throw ValidationException::withMessages(['ec' => ['Un syllabus existe déjà pour cet EC et cette année.']]);
        }

        $syllabus = DB::transaction(function () use ($request, $ec, $annee, $audit) {
            $syllabus = Syllabus::create([
                'ec_id' => $ec->id,
                'annee_universitaire_id' => $annee->id,
                'statut' => Statut::Brouillon,
                'created_by' => $request->user()->id,
            ])->refresh();

            $audit->journaliser(
                $request->user(), 'creation', $syllabus, null,
                ['ec' => $ec->code, 'annee' => $annee->libelle],
                "Création d'un syllabus"
            );

            return $syllabus;
        });

        return (new SyllabusResource($syllabus->load(['ec', 'anneeUniversitaire', 'enseignants', 'modificateur'])))
            ->response()
            ->setStatusCode(201);
    }

    /** Suppression réservée aux brouillons : un syllabus publié s'archive (PUT avec statut = archive). */
    public function destroy(Request $request, Syllabus $syllabus, AuditService $audit): Response
    {
        if ($syllabus->statut !== Statut::Brouillon) {
            abort(409, "Seul un syllabus en brouillon peut être supprimé : un syllabus publié s'archive.");
        }

        DB::transaction(function () use ($request, $syllabus, $audit) {
            $audit->journaliser(
                $request->user(), 'suppression', $syllabus,
                ['ec' => $syllabus->ec->code, 'annee' => $syllabus->anneeUniversitaire->libelle], null,
                "Suppression d'un brouillon de syllabus"
            );
            $syllabus->delete(); // ses autorisations sont supprimées en cascade
        });

        return response()->noContent();
    }
}
