<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Niveau;
use App\Models\Parcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

/** La liste des parcours d'un niveau indique lesquels ont une maquette publiée. */
class ParcoursDisponibiliteTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private function parcoursDuNiveau(Niveau $niveau): array
    {
        return collect($this->getJson("/api/niveaux/{$niveau->public_id}/parcours")->assertOk()->json('data'))
            ->keyBy('code')
            ->all();
    }

    public function test_un_parcours_avec_une_maquette_publiee_est_disponible(): void
    {
        $d = $this->creerDonnees(Statut::Publie);

        $this->assertSame(1, $this->parcoursDuNiveau($d['niveau'])['P-TEST']['maquettes_publiees_count']);
    }

    public function test_un_parcours_sans_maquette_ou_avec_un_brouillon_n_est_pas_disponible(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $sansMaquette = Parcours::create(['code' => 'P-VIDE', 'nom' => 'Sans maquette']);
        $sansMaquette->niveaux()->attach($d['niveau']->id);

        $liste = $this->parcoursDuNiveau($d['niveau']);

        $this->assertSame(0, $liste['P-TEST']['maquettes_publiees_count']);
        $this->assertSame(0, $liste['P-VIDE']['maquettes_publiees_count']);
    }

    public function test_une_maquette_archivee_ne_compte_pas(): void
    {
        $d = $this->creerDonnees(Statut::Archive);

        $this->assertSame(0, $this->parcoursDuNiveau($d['niveau'])['P-TEST']['maquettes_publiees_count']);
    }

    public function test_seules_les_maquettes_du_niveau_consulte_comptent(): void
    {
        $d = $this->creerDonnees(Statut::Publie); // maquette publiée pour L2 uniquement
        $l3 = Niveau::create(['code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3]);
        $d['parcours']->niveaux()->attach($l3->id);

        $this->assertSame(1, $this->parcoursDuNiveau($d['niveau'])['P-TEST']['maquettes_publiees_count']);
        $this->assertSame(0, $this->parcoursDuNiveau($l3)['P-TEST']['maquettes_publiees_count']);
    }
}
