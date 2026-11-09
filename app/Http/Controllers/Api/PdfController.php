<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maquette;
use App\Models\Syllabus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Téléchargement des maquettes et des syllabus en PDF.
 * Les documents publiés sont publics ; les autres sont réservés aux personnes autorisées.
 */
class PdfController extends Controller
{
    public function maquette(Request $request, Maquette $maquette)
    {
        abort_unless(
            $this->estPublie($maquette) || $this->estAdmin($request->user('sanctum')),
            404
        );

        // Mêmes relations que la consultation de la maquette dans l'API
        $maquette->load(['niveau', 'parcours', 'anneeUniversitaire', 'colonnes', 'unites.semestre', 'unites.lignes.ec']);

        $pdf = Pdf::loadView('pdf.maquette', ['maquette' => $maquette, 'logo' => $this->logo()])
            ->setPaper('a4', 'landscape');

        $nom = Str::slug(implode(' ', array_filter([
            $maquette->niveau?->libelle, $maquette->parcours?->nom, $maquette->anneeUniversitaire?->libelle,
        ])));

        return $pdf->download('maquette-'.($nom ?: $maquette->public_id).'.pdf');
    }

    public function syllabus(Request $request, Syllabus $syllabus)
    {
        $utilisateur = $request->user('sanctum');

        abort_unless(
            $this->estPublie($syllabus)
                || $this->estAdmin($utilisateur)
                || ($utilisateur !== null && $utilisateur->can('update', $syllabus)),
            404
        );

        $syllabus->load(['ec', 'anneeUniversitaire']);

        $pdf = Pdf::loadView('pdf.syllabus', ['syllabus' => $syllabus, 'logo' => $this->logo()])
            ->setPaper('a4', 'portrait');

        $nom = Str::slug((string) $syllabus->ec?->intitule);

        return $pdf->download('syllabus-'.($nom ?: $syllabus->public_id).'.pdf');
    }

    /** Le statut est une énumération : on compare sa valeur, jamais l'objet. */
    private function estPublie($modele): bool
    {
        return str_starts_with((string) $modele->statut->value, 'publi');
    }

    private function estAdmin($utilisateur): bool
    {
        if ($utilisateur === null || ! $utilisateur->is_active) {
            return false;
        }

        $role = $utilisateur->role;
        $role = $role instanceof \BackedEnum ? $role->value : (string) $role;

        return str_contains(strtolower($role), 'admin');
    }

    /** Logo intégré au PDF (vide si le fichier est absent). */
    private function logo(): ?string
    {
        $fichier = public_path('logo-istd.jpg');

        return file_exists($fichier)
            ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($fichier))
            : null;
    }
}
