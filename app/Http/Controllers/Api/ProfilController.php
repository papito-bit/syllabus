<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;

class ProfilController extends Controller
{
    public function update(UpdateProfileRequest $request, AuditService $audit): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty()) {
            $anciennes = array_intersect_key($user->getOriginal(), $user->getDirty());
            $nouvelles = $user->getDirty();
            $user->save();

            $audit->journaliser($user, 'modification_profil', $user, $anciennes, $nouvelles, 'Modification du profil');
        }

        return new UserResource($user);
    }

    public function motDePasse(ChangePasswordRequest $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]); // haché automatiquement

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Aucun mot de passe dans le journal
        $audit->journaliser($user, 'changement_mot_de_passe', $user, null, null, 'Changement de mot de passe');

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
