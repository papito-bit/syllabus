<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion par session (cookie). Le frontend doit d'abord appeler GET /sanctum/csrf-cookie.
     * Limité à 5 essais par minute (limiteur "login").
     */
    public function login(LoginRequest $request, AuditService $audit): UserResource
    {
        // Sans session (requête ne venant pas du frontend déclaré), la connexion est impossible
        if (! $request->hasSession()) {
            abort(419, "Connexion possible uniquement depuis l'application web.");
        }

        $identifiants = [
            'email' => trim($request->validated('email')),
            'password' => $request->validated('password'),
            'is_active' => true, // un compte désactivé ne peut pas se connecter
        ];

        if (! Auth::guard('web')->attempt($identifiants)) {
            // Même message dans tous les cas : on ne révèle pas si l'e-mail existe
            throw ValidationException::withMessages([
                'email' => ['Adresse e-mail ou mot de passe incorrect.'],
            ]);
        }

        // Nouvelle session après connexion (protection contre la fixation de session)
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $audit->journaliser($user, 'connexion', $user, null, null, 'Connexion');

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    /** Utilisateur connecté (sert au frontend à restaurer la session au chargement). */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
