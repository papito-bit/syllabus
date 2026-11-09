<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Authentification SPA par cookies (Sanctum) : sessions + protection CSRF
        $middleware->statefulApi();

        // Alias utilisés dans routes/api.php
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'actif' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toute erreur sous /api est renvoyée en JSON, jamais en page HTML
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 401 : utilisateur non connecté
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Authentification requise.'], 401);
            }

            return null;
        });

        // 403, 404, 405, 419, 429... : messages en français, sans détails techniques
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $messages = [
                401 => 'Authentification requise.',
                403 => "Vous n'avez pas le droit d'effectuer cette action.",
                404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.',
                419 => 'Session expirée. Rechargez la page puis réessayez.',
                429 => 'Trop de requêtes. Patientez quelques instants.',
            ];
            $messagesAnglaisParDefaut = ['', 'This action is unauthorized.', 'CSRF token mismatch.', 'Unauthenticated.'];

            $message = $e->getMessage();
            if (in_array($status, [404, 405, 429], true) || in_array($message, $messagesAnglaisParDefaut, true)) {
                $message = $messages[$status] ?? ($message !== '' ? $message : 'Erreur.');
            }

            return response()->json(['message' => $message], $status, $e->getHeaders());
        });
    })->create();
