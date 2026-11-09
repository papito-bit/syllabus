<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAutorisationRequest;
use App\Http\Resources\AutorisationResource;
use App\Models\Syllabus;
use App\Models\SyllabusAutorisation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Gestion des droits d'édition des enseignants, syllabus par syllabus (administrateur). */
class AutorisationController extends Controller
{
    public function index(Request $request, Syllabus $syllabus): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageAutorisations', $syllabus);

        $autorisations = $syllabus->autorisations()
            ->whereNull('revoque_le')
            ->with(['user', 'accordePar'])
            ->orderByDesc('est_responsable')
            ->orderBy('accorde_le')
            ->get();

        return AutorisationResource::collection($autorisations);
    }

    public function store(StoreAutorisationRequest $request, Syllabus $syllabus, AuditService $audit): JsonResponse
    {
        $enseignant = User::query()->where('public_id', $request->validated('user'))->firstOrFail();

        if (! $enseignant->estEnseignant() || ! $enseignant->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Seul un compte enseignant actif peut recevoir une autorisation.'],
            ]);
        }

        $estResponsable = (bool) ($request->validated('est_responsable') ?? false);
        $admin = $request->user();

        [$autorisation, $dejaActive] = DB::transaction(function () use ($syllabus, $enseignant, $estResponsable, $admin, $audit) {
            // Un seul enseignant responsable par syllabus
            if ($estResponsable) {
                SyllabusAutorisation::query()
                    ->where('syllabus_id', $syllabus->id)
                    ->where('user_id', '!=', $enseignant->id)
                    ->whereNull('revoque_le')
                    ->update(['est_responsable' => false]);
            }

            $autorisation = SyllabusAutorisation::firstOrNew([
                'syllabus_id' => $syllabus->id,
                'user_id' => $enseignant->id,
            ]);

            $dejaActive = $autorisation->exists && $autorisation->revoque_le === null;

            $attributs = ['est_responsable' => $estResponsable, 'revoque_le' => null];
            if (! $dejaActive) {
                $attributs['accorde_le'] = now();
                $attributs['accorde_par'] = $admin->id;
            }
            $autorisation->fill($attributs)->save();

            $audit->journaliser(
                $admin,
                $dejaActive ? 'modification_autorisation' : 'octroi_autorisation',
                $syllabus,
                null,
                ['enseignant' => $enseignant->public_id, 'est_responsable' => $estResponsable],
                $dejaActive ? "Modification d'une autorisation" : "Autorisation accordée à un enseignant"
            );

            return [$autorisation, $dejaActive];
        });

        $autorisation->load(['user', 'accordePar']);

        return (new AutorisationResource($autorisation))
            ->response()
            ->setStatusCode($dejaActive ? 200 : 201);
    }

    /** Retrait : l'autorisation est révoquée (conservée pour la traçabilité), jamais effacée. */
    public function destroy(Request $request, Syllabus $syllabus, User $user, AuditService $audit): Response
    {
        Gate::forUser($request->user())->authorize('manageAutorisations', $syllabus);

        $autorisation = SyllabusAutorisation::query()
            ->where('syllabus_id', $syllabus->id)
            ->where('user_id', $user->id)
            ->whereNull('revoque_le')
            ->firstOrFail();

        DB::transaction(function () use ($autorisation, $syllabus, $user, $request, $audit) {
            $autorisation->update(['revoque_le' => now(), 'est_responsable' => false]);

            $audit->journaliser(
                $request->user(),
                'retrait_autorisation',
                $syllabus,
                ['enseignant' => $user->public_id],
                null,
                'Autorisation retirée à un enseignant'
            );
        });

        return response()->noContent();
    }
}
