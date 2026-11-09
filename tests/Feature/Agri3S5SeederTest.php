<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Ec;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\MaquetteColonne;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Models\Syllabus;
use App\Models\UniteEnseignement;
use Database\Seeders\Agri3S5Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vérifie le chargement de la maquette AGRI3 (S5) et de ses syllabus, et son affichage par l'API publique. */
class Agri3S5SeederTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        $niveau = Niveau::where('code', 'L3')->firstOrFail();
        $parcours = Parcours::where('code', 'AGRI3')->firstOrFail();

        return "/api/niveaux/{$niveau->public_id}/parcours/{$parcours->public_id}/maquette";
    }

    public function test_le_fichier_de_donnees_est_complet(): void
    {
        $donnees = json_decode((string) file_get_contents(database_path('seeders/data/agri3_s5.json')), true, 512, JSON_THROW_ON_ERROR);

        $lignes = collect($donnees['unites'])->flatMap(fn ($ue) => $ue['lignes']);

        $this->assertCount(5, $donnees['unites']);
        $this->assertCount(17, $lignes);
        $this->assertCount(17, $lignes->pluck('code')->unique());

        foreach ($lignes as $ligne) {
            foreach (['objectifs', 'prerequis', 'programme', 'bibliographie'] as $rubrique) {
                $this->assertNotSame('', trim($ligne['syllabus'][$rubrique]), "{$ligne['intitule']} : {$rubrique} vide");
                $this->assertStringNotContainsString('*', $ligne['syllabus'][$rubrique]);
            }
        }
    }

    public function test_charge_la_maquette_et_les_syllabus(): void
    {
        $this->seed(Agri3S5Seeder::class);

        $this->assertSame(1, Maquette::count());
        $this->assertSame(Statut::Publie, Maquette::first()->statut);
        $this->assertSame(5, UniteEnseignement::count());
        $this->assertSame(17, LigneMaquette::count());
        $this->assertSame(17, Ec::count());
        $this->assertSame(17, Syllabus::count());
        $this->assertSame(2, MaquetteColonne::count());
        $this->assertSame(17, Syllabus::publies()->count());
    }

    public function test_l_api_renvoie_la_maquette_avec_la_structure_du_document(): void
    {
        $this->seed(Agri3S5Seeder::class);

        $reponse = $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.parcours.nom', 'AGRI3')
            ->assertJsonPath('data.niveau.code', 'L3')
            ->assertJsonPath('data.semestres.0.semestre.code', 'S5')
            ->assertJsonPath('data.colonnes.0.cle', 'autres')
            ->assertJsonPath('data.colonnes.1.cle', 'travail_personnel')
            ->assertJsonCount(5, 'data.semestres.0.unites');

        $unites = $reponse->json('data.semestres.0.unites');

        $this->assertSame(['UE20', 'UE21', 'UE22', 'UE23', 'UE24'], array_column($unites, 'code'));
        $this->assertSame([4, 3, 4, 3, 3], array_map(fn ($u) => count($u['lignes']), $unites));
        $this->assertSame('60', $unites[0]['lignes'][0]['donnees_supplementaires']['travail_personnel']);
        $this->assertSame('45', $unites[1]['lignes'][2]['donnees_supplementaires']['travail_personnel']);
    }

    public function test_les_totaux_et_les_credits_sont_ceux_du_document_corriges_des_erreurs_de_calcul(): void
    {
        $this->seed(Agri3S5Seeder::class);

        $unites = $this->getJson($this->url())->json('data.semestres.0.unites');

        // Le document indique 110,5 pour UE22, mais 30 + 22,5 + 30 + 30 = 112,5 : le total est recalculé.
        $attendus = ['UE20' => 120, 'UE21' => 67.5, 'UE22' => 112.5, 'UE23' => 90, 'UE24' => 60];

        foreach ($unites as $unite) {
            $total = array_sum(array_map('floatval', array_column($unite['lignes'], 'heures_total')));
            $this->assertEqualsWithDelta($attendus[$unite['code']], $total, 0.001, "Total de {$unite['code']}");
        }

        $credits = array_sum(array_map(fn ($u) => (float) $u['credits'], $unites));
        $this->assertEqualsWithDelta(30, $credits, 0.001);
    }

    public function test_chaque_ec_a_un_syllabus_public_avec_ses_quatre_rubriques(): void
    {
        $this->seed(Agri3S5Seeder::class);

        $unites = $this->getJson($this->url())->json('data.semestres.0.unites');
        $nombre = 0;

        foreach ($unites as $unite) {
            foreach ($unite['lignes'] as $ligne) {
                $this->assertNotNull($ligne['syllabus_id'], "{$ligne['ec']['intitule']} n'a pas de syllabus");

                $this->getJson('/api/syllabus/'.$ligne['syllabus_id'])
                    ->assertOk()
                    ->assertJsonPath('data.ec.intitule', $ligne['ec']['intitule'])
                    ->assertJsonPath('data.statut', 'publie');

                $syllabus = $this->getJson('/api/syllabus/'.$ligne['syllabus_id'])->json('data');
                foreach (['objectifs', 'prerequis', 'programme', 'bibliographie'] as $rubrique) {
                    $this->assertNotEmpty($syllabus[$rubrique], "{$ligne['ec']['intitule']} : {$rubrique}");
                }
                $nombre++;
            }
        }

        $this->assertSame(17, $nombre);
    }

    public function test_le_chargement_peut_etre_relance_sans_doublon(): void
    {
        $this->seed(Agri3S5Seeder::class);
        $this->seed(Agri3S5Seeder::class);

        $this->assertSame(1, Maquette::count());
        $this->assertSame(5, UniteEnseignement::count());
        $this->assertSame(17, LigneMaquette::count());
        $this->assertSame(17, Syllabus::count());
        $this->assertSame(1, Parcours::where('code', 'AGRI3')->count());
    }
}
