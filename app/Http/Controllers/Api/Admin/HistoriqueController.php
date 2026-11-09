<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HistoriqueResource;
use App\Models\HistoriqueModification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consultation de l'historique des modifications (lecture seule).
 * Filtres : ?entite_type=syllabus&action=modification&user=<id>&entite=<id>&du=2026-01-01&au=2026-12-31
 */
class HistoriqueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'entite_type' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:30'],
            'user' => ['nullable', 'string', 'size:26'],
            'entite' => ['nullable', 'string', 'size:26'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $requete = HistoriqueModification::query()
            ->with('user:id,public_id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('entite_type')) {
            $requete->where('entite_type', $request->string('entite_type')->toString());
        }
        if ($request->filled('action')) {
            $requete->where('action', $request->string('action')->toString());
        }
        if ($request->filled('entite')) {
            $requete->where('entite_public_id', $request->string('entite')->toString());
        }
        if ($request->filled('user')) {
            $requete->whereHas('user', fn ($q) => $q->where('public_id', $request->string('user')->toString()));
        }
        if ($request->filled('du')) {
            $requete->whereDate('created_at', '>=', $request->string('du')->toString());
        }
        if ($request->filled('au')) {
            $requete->whereDate('created_at', '<=', $request->string('au')->toString());
        }

        return HistoriqueResource::collection($requete->paginate($request->integer('per_page', 25)));
    }
}
