<?php

namespace App\Http\Controllers\Api;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyllabusListeResource;
use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Les syllabus que l'utilisateur connecté a le droit de modifier (autorisations actives). */
class MesSyllabusController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'statut' => ['nullable', Rule::enum(Statut::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $requete = Syllabus::query()
            ->whereHas('autorisations', fn ($q) => $q->where('user_id', $user->id)->whereNull('revoque_le'))
            ->with(['ec', 'anneeUniversitaire', 'enseignants'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($request->filled('statut')) {
            $requete->where('statut', $request->string('statut')->toString());
        }

        return SyllabusListeResource::collection($requete->paginate($request->integer('per_page', 50)));
    }
}
