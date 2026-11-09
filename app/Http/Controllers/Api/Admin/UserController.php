<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes. Il n'y a volontairement pas de suppression : un compte se désactive,
 * pour conserver l'historique des modifications et des autorisations.
 */
class UserController extends Controller
{
    /** Liste paginée : ?q=texte&role=admin|enseignant&actif=0|1&per_page=20 */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', User::class);

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'actif' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $requete = User::query()->orderBy('name')->orderBy('id');

        if ($request->filled('q')) {
            $terme = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
            $requete->where(function ($condition) use ($terme) {
                $condition->where('name', 'like', $terme)->orWhere('email', 'like', $terme);
            });
        }
        if ($request->filled('role')) {
            $requete->where('role', $request->string('role')->toString());
        }
        if ($request->filled('actif')) {
            $requete->where('is_active', $request->boolean('actif'));
        }

        return UserResource::collection($requete->paginate($request->integer('per_page', 20)));
    }

    /**
     * Création d'un compte. Sans mot de passe fourni, un mot de passe temporaire est généré
     * et renvoyé UNE SEULE FOIS dans la réponse (il n'est stocké nulle part en clair).
     */
    public function store(UserRequest $request, AuditService $audit): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', User::class);

        $donnees = $request->validated();
        $motDePasse = $donnees['password'] ?? null;
        $genere = $motDePasse === null;
        if ($genere) {
            $motDePasse = Str::password(16);
        }

        $user = DB::transaction(function () use ($request, $donnees, $motDePasse, $audit) {
            $user = new User([
                'name' => $donnees['name'],
                'email' => $donnees['email'],
                'password' => $motDePasse, // haché automatiquement
            ]);
            // role et is_active ne sont pas assignables en masse : on les fixe explicitement
            $user->forceFill([
                'role' => Role::from($donnees['role']),
                'is_active' => $donnees['is_active'] ?? true,
            ])->save();

            // Jamais de mot de passe dans le journal
            $audit->journaliser(
                $request->user(), 'creation_utilisateur', $user, null,
                ['email' => $user->email, 'role' => $donnees['role'], 'is_active' => (bool) ($donnees['is_active'] ?? true)],
                "Création d'un compte"
            );

            return $user->refresh();
        });

        $reponse = ['data' => (new UserResource($user))->resolve($request)];
        if ($genere) {
            $reponse['mot_de_passe_temporaire'] = $motDePasse;
        }

        return response()->json($reponse, 201)->header('Cache-Control', 'no-store');
    }

    public function update(UserRequest $request, User $user, AuditService $audit): UserResource
    {
        $acteur = $request->user();
        Gate::forUser($acteur)->authorize('update', $user);

        $donnees = $request->validated();
        $nouveauRole = Role::from($donnees['role']);
        $nouvelEtat = array_key_exists('is_active', $donnees) ? (bool) $donnees['is_active'] : (bool) $user->is_active;

        // Un administrateur ne peut pas se retirer ses propres droits ni se désactiver
        if ($acteur->is($user)) {
            if ($nouveauRole !== $user->role) {
                throw ValidationException::withMessages(['role' => ['Vous ne pouvez pas modifier votre propre rôle.']]);
            }
            if (! $nouvelEtat) {
                throw ValidationException::withMessages(['is_active' => ['Vous ne pouvez pas désactiver votre propre compte.']]);
            }
        }

        // Il doit toujours rester au moins un administrateur actif
        $perdUnAdministrateurActif = $user->estAdmin() && $user->is_active
            && ($nouveauRole !== Role::Admin || ! $nouvelEtat);
        if ($perdUnAdministrateurActif
            && User::where('role', Role::Admin->value)->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => ['Il doit rester au moins un administrateur actif.']]);
        }

        $etaitActif = (bool) $user->is_active;

        DB::transaction(function () use ($user, $donnees, $nouveauRole, $nouvelEtat, $acteur, $audit, $etaitActif) {
            $user->fill(Arr::only($donnees, ['name', 'email']));
            $user->forceFill(['role' => $nouveauRole, 'is_active' => $nouvelEtat]);

            $champs = array_keys($user->getDirty());
            if ($champs === []) {
                return;
            }

            $anciennes = Arr::only($user->getOriginal(), $champs);
            $nouvelles = $user->only($champs);
            $user->save();

            // Un compte désactivé est déconnecté immédiatement
            if ($etaitActif && ! $nouvelEtat) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $audit->journaliser($acteur, 'modification_utilisateur', $user, $anciennes, $nouvelles, "Modification d'un compte");
        });

        return new UserResource($user->refresh());
    }

    /** Réinitialisation : génère un mot de passe temporaire, affiché une seule fois. */
    public function reinitialiserMotDePasse(Request $request, User $user, AuditService $audit): JsonResponse
    {
        $acteur = $request->user();
        Gate::forUser($acteur)->authorize('resetPassword', $user);

        if ($acteur->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['Pour changer votre propre mot de passe, utilisez la page de votre profil.'],
            ]);
        }

        $motDePasse = Str::password(16);

        DB::transaction(function () use ($user, $motDePasse, $acteur, $audit) {
            $user->update(['password' => $motDePasse]); // haché automatiquement

            // Toutes les sessions ouvertes de ce compte sont coupées
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $audit->journaliser($acteur, 'reinitialisation_mot_de_passe', $user, null, null, 'Mot de passe réinitialisé par un administrateur');
        });

        return response()->json([
            'data' => (new UserResource($user->refresh()))->resolve($request),
            'mot_de_passe_temporaire' => $motDePasse,
        ])->header('Cache-Control', 'no-store');
    }
    /**
     * Suppression définitive d'un compte. Refusée pour son propre compte,
     * et pour un compte déjà lié à des données (historique, autorisations).
     */
    public function destroy(\Illuminate\Http\Request $request, \App\Models\User $user)
    {
        if ($request->user()->is($user)) {
            abort(422, 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            abort(409, 'Ce compte est lié à des données (historique, autorisations) et ne peut pas être supprimé. Désactivez-le plutôt.');
        }

        return response()->json(['message' => 'Compte supprimé.']);
    }
    }
