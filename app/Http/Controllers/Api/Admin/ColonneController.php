<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ModifieUneMaquette;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ColonnesRequest;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\MaquetteColonne;
use App\Models\UniteEnseignement;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Colonnes supplémentaires propres à une maquette (au-delà des colonnes standard). */
class ColonneController extends Controller
{
    use ModifieUneMaquette;

    /** Remplace la liste complète des colonnes. Les valeurs des colonnes retirées sont supprimées. */
    public function update(ColonnesRequest $request, Maquette $maquette, AuditService $audit): JsonResponse
    {
        $this->exigerBrouillon($maquette);

        $nouvelles = collect($request->validated('colonnes'))->values();
        $anciennes = $maquette->colonnes()->get();

        $avant = $anciennes->map(fn ($c) => [$c->cle, $c->libelle])->values()->all();
        $apres = $nouvelles->map(fn ($c) => [$c['cle'], $c['libelle']])->values()->all();

        DB::transaction(function () use ($request, $maquette, $nouvelles, $anciennes, $avant, $apres, $audit) {
            $clesConservees = $nouvelles->pluck('cle')->all();
            $retirees = $anciennes->pluck('cle')->diff($clesConservees)->values()->all();

            MaquetteColonne::where('maquette_id', $maquette->id)->whereNotIn('cle', $clesConservees)->delete();

            foreach ($nouvelles as $index => $colonne) {
                MaquetteColonne::updateOrCreate(
                    ['maquette_id' => $maquette->id, 'cle' => $colonne['cle']],
                    ['libelle' => $colonne['libelle'], 'ordre' => $index]
                );
            }

            if ($retirees !== []) {
                $this->supprimerValeurs($maquette, $retirees);
            }

            if ($avant !== $apres) {
                $audit->journaliser(
                    $request->user(), 'modification_colonnes', $maquette,
                    ['colonnes' => $anciennes->pluck('cle')->all()],
                    ['colonnes' => $clesConservees],
                    'Modification des colonnes supplémentaires'
                );
            }
        });

        return response()->json([
            'data' => $maquette->colonnes()->get()->map(fn ($c) => [
                'cle' => $c->cle,
                'libelle' => $c->libelle,
                'ordre' => $c->ordre,
            ])->values(),
        ]);
    }

    /** Retire les valeurs des colonnes supprimées (UE et lignes de cette maquette). */
    private function supprimerValeurs(Maquette $maquette, array $cles): void
    {
        $unites = UniteEnseignement::where('maquette_id', $maquette->id)->get();

        foreach ($unites as $unite) {
            $this->retirerCles($unite, $cles);
        }
        foreach (LigneMaquette::whereIn('ue_id', $unites->pluck('id'))->get() as $ligne) {
            $this->retirerCles($ligne, $cles);
        }
    }

    private function retirerCles(Model $modele, array $cles): void
    {
        $donnees = $modele->donnees_supplementaires;
        if (! is_array($donnees)) {
            return;
        }

        $reste = Arr::except($donnees, $cles);
        if ($reste !== $donnees) {
            $modele->donnees_supplementaires = $reste === [] ? null : $reste;
            $modele->save();
        }
    }
}
