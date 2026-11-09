<?php

namespace Tests\Concerns;

use App\Models\Ec;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\Semestre;
use App\Models\UniteEnseignement;

/** Outils de tests pour remplir une maquette et simuler un visiteur. */
trait CreeContenu
{
    /** Ajoute une UE contenant un EC (20 h au total) à la maquette donnée. */
    protected function ajouterContenu(Maquette $maquette, Semestre $semestre, string $codeUe = 'UE9', string $codeEc = 'EC9'): array
    {
        $ue = UniteEnseignement::create([
            'maquette_id' => $maquette->id,
            'semestre_id' => $semestre->id,
            'code' => $codeUe,
            'intitule' => 'UE '.$codeUe,
            'ordre' => 1,
        ]);

        $ec = Ec::firstOrCreate(['code' => $codeEc], ['intitule' => 'EC '.$codeEc]);

        $ligne = LigneMaquette::create([
            'ue_id' => $ue->id,
            'ec_id' => $ec->id,
            'ordre' => 1,
            'heures_cm' => 10,
            'heures_td' => 5,
            'heures_tp' => 5,
            'heures_total' => 20,
        ]);

        return compact('ue', 'ec', 'ligne');
    }

    /** Redevient un simple visiteur (les gardes d'authentification gardent l'utilisateur d'une requête à l'autre dans un test). */
    protected function enTantQueVisiteur(): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this;
    }
}
