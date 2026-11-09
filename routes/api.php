<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AnneeUniversitaireController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutorisationController;
use App\Http\Controllers\Api\MaquetteController;
use App\Http\Controllers\Api\MesSyllabusController;
use App\Http\Controllers\Api\NiveauController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\SyllabusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST (préfixe /api ajouté automatiquement par Laravel)
|--------------------------------------------------------------------------
| Réponses : { "data": ... } en succès, { "message": "...", "errors": {...} } en erreur.
| Les identifiants exposés sont toujours des identifiants publics (ULID).
*/

Route::middleware('throttle:api')->group(function () {

    // --- Connexion (5 essais par minute, voir AppServiceProvider) ---
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // --- Consultation publique : uniquement les données publiées ---
    Route::get('/niveaux', [NiveauController::class, 'index']);
    Route::get('/niveaux/{niveau}/parcours', [NiveauController::class, 'parcours']);
    Route::get('/niveaux/{niveau}/parcours/{parcours}/maquette', [MaquetteController::class, 'courante']);
    Route::get('/annees', [AnneeUniversitaireController::class, 'index']);
    Route::get('/maquettes', [MaquetteController::class, 'index']);
    Route::get('/maquettes/{maquette}', [MaquetteController::class, 'show']);
    Route::get('/syllabus/{syllabus}', [SyllabusController::class, 'show']);

    // Téléchargement PDF (publié : public ; brouillon : personnes autorisées, voir PdfController)
    Route::get('/maquettes/{maquette}/pdf', [PdfController::class, 'maquette']);
    Route::get('/syllabus/{syllabus}/pdf', [PdfController::class, 'syllabus']);

    // --- Utilisateurs connectés (enseignants et administrateurs) ---
    Route::middleware(['auth:sanctum', 'actif'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'me']);
        Route::put('/profil', [ProfilController::class, 'update']);
        Route::put('/profil/mot-de-passe', [ProfilController::class, 'motDePasse']);

        // Les syllabus que l'utilisateur peut modifier
        Route::get('/mes-syllabus', [MesSyllabusController::class, 'index']);

        // Modification d'un syllabus : le droit est vérifié par la Policy, pas par la route
        Route::put('/syllabus/{syllabus}', [SyllabusController::class, 'update']);

        // --- Administration (middleware "admin" + Policies / FormRequests) ---
        Route::middleware('admin')->prefix('admin')->group(function () {

            // Syllabus : liste, création en brouillon, suppression d'un brouillon, autorisations
            Route::get('/syllabus', [Admin\SyllabusController::class, 'index']);
            Route::post('/syllabus', [Admin\SyllabusController::class, 'store']);
            Route::delete('/syllabus/{syllabus}', [Admin\SyllabusController::class, 'destroy']);
            Route::get('/syllabus/{syllabus}/autorisations', [AutorisationController::class, 'index']);
            Route::post('/syllabus/{syllabus}/autorisations', [AutorisationController::class, 'store']);
            Route::delete('/syllabus/{syllabus}/autorisations/{user}', [AutorisationController::class, 'destroy']);

            // Maquettes : cycle de vie (brouillon, publiée, archivée)
            Route::post('/maquettes', [Admin\MaquetteController::class, 'store']);
            Route::put('/maquettes/{maquette}', [Admin\MaquetteController::class, 'update']);
            Route::delete('/maquettes/{maquette}', [Admin\MaquetteController::class, 'destroy']);
            Route::post('/maquettes/{maquette}/publier', [Admin\MaquetteController::class, 'publier']);
            Route::post('/maquettes/{maquette}/archiver', [Admin\MaquetteController::class, 'archiver']);
            Route::post('/maquettes/{maquette}/dupliquer', [Admin\MaquetteController::class, 'dupliquer']);
            Route::put('/maquettes/{maquette}/colonnes', [Admin\ColonneController::class, 'update']);

            // Contenu d'une maquette en brouillon : UE et lignes d'EC
            Route::post('/maquettes/{maquette}/unites', [Admin\UniteController::class, 'store']);
            Route::put('/unites/{unite}', [Admin\UniteController::class, 'update']);
            Route::delete('/unites/{unite}', [Admin\UniteController::class, 'destroy']);
            Route::post('/unites/{unite}/lignes', [Admin\LigneController::class, 'store']);
            Route::put('/lignes/{ligne}', [Admin\LigneController::class, 'update']);
            Route::delete('/lignes/{ligne}', [Admin\LigneController::class, 'destroy']);

            // Référentiel
            Route::get('/niveaux', [Admin\NiveauController::class, 'index']);
            Route::post('/niveaux', [Admin\NiveauController::class, 'store']);
            Route::put('/niveaux/{niveau}', [Admin\NiveauController::class, 'update']);
            Route::delete('/niveaux/{niveau}', [Admin\NiveauController::class, 'destroy']);

            Route::get('/parcours', [Admin\ParcoursController::class, 'index']);
            Route::post('/parcours', [Admin\ParcoursController::class, 'store']);
            Route::put('/parcours/{parcours}', [Admin\ParcoursController::class, 'update']);
            Route::delete('/parcours/{parcours}', [Admin\ParcoursController::class, 'destroy']);

            Route::get('/annees', [Admin\AnneeUniversitaireController::class, 'index']);
            Route::post('/annees', [Admin\AnneeUniversitaireController::class, 'store']);
            Route::put('/annees/{annee}', [Admin\AnneeUniversitaireController::class, 'update']);
            Route::delete('/annees/{annee}', [Admin\AnneeUniversitaireController::class, 'destroy']);

            Route::get('/semestres', [Admin\SemestreController::class, 'index']);
            Route::post('/semestres', [Admin\SemestreController::class, 'store']);
            Route::put('/semestres/{semestre}', [Admin\SemestreController::class, 'update']);
            Route::delete('/semestres/{semestre}', [Admin\SemestreController::class, 'destroy']);

            Route::get('/ecs', [Admin\EcController::class, 'index']);
            Route::post('/ecs', [Admin\EcController::class, 'store']);
            Route::put('/ecs/{ec}', [Admin\EcController::class, 'update']);
            Route::delete('/ecs/{ec}', [Admin\EcController::class, 'destroy']);

            // Comptes utilisateurs (désactivation, ou suppression si le compte n'a pas d'historique)
            Route::get('/utilisateurs', [Admin\UserController::class, 'index']);
            Route::post('/utilisateurs', [Admin\UserController::class, 'store']);
            Route::put('/utilisateurs/{user}', [Admin\UserController::class, 'update']);
            Route::post('/utilisateurs/{user}/mot-de-passe', [Admin\UserController::class, 'reinitialiserMotDePasse']);
            Route::delete('/utilisateurs/{user}', [Admin\UserController::class, 'destroy']);

            // Historique des modifications (lecture seule)
            Route::get('/historique', [Admin\HistoriqueController::class, 'index']);
        });
    });
});
