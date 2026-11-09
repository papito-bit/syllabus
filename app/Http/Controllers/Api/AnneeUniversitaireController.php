<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnneeUniversitaireResource;
use App\Models\AnneeUniversitaire;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnneeUniversitaireController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AnneeUniversitaireResource::collection(
            AnneeUniversitaire::query()->orderByDesc('libelle')->get()
        );
    }
}
