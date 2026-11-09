<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class HistoriqueAdminTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const URL = '/api/admin/historique';

    public function test_l_historique_liste_les_actions_avec_des_identifiants_publics(): void
    {
        $admin = $this->administrateur();
        $idNiveau = $this->enTantQue($admin)
            ->postJson('/api/admin/niveaux', ['code' => 'M1', 'libelle' => 'Master 1'])->json('data.id');
        $this->enTantQue($admin)->postJson('/api/admin/semestres', ['code' => 'S9', 'libelle' => 'Semestre 9']);

        $reponse = $this->enTantQue($admin)->getJson(self::URL)
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.entite_type', 'semestre') // le plus récent d'abord
            ->assertJsonPath('data.0.action', 'creation')
            ->assertJsonPath('data.0.utilisateur.name', $admin->name)
            ->assertJsonPath('data.0.utilisateur.id', $admin->public_id)
            ->assertJsonPath('data.1.entite_id', $idNiveau)
            ->assertJsonMissingPath('data.0.id');

        $this->assertSame(26, strlen($reponse->json('data.0.entite_id')));
        $this->assertSame(26, strlen($reponse->json('data.0.utilisateur.id')));
    }

    public function test_les_filtres_de_l_historique(): void
    {
        $admin = $this->administrateur();
        $autre = $this->administrateur();
        $idNiveau = $this->enTantQue($admin)
            ->postJson('/api/admin/niveaux', ['code' => 'M1', 'libelle' => 'Master 1'])->json('data.id');
        $this->enTantQue($admin)->postJson('/api/admin/semestres', ['code' => 'S9', 'libelle' => 'Semestre 9']);

        $this->enTantQue($admin)->getJson(self::URL.'?entite_type=niveau')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?entite='.$idNiveau)->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?action=creation')->assertOk()->assertJsonCount(2, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?action=suppression')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?user='.$admin->public_id)->assertOk()->assertJsonCount(2, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?user='.$autre->public_id)->assertOk()->assertJsonCount(0, 'data');

        $aujourdhui = now()->toDateString();
        $this->enTantQue($admin)->getJson(self::URL.'?du='.$aujourdhui)->assertOk()->assertJsonCount(2, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?du=2999-01-01')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?au=2000-01-01')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?per_page=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_les_filtres_invalides_sont_refuses(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->getJson(self::URL.'?user=abc')->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->enTantQue($admin)->getJson(self::URL.'?entite=abc')->assertUnprocessable()->assertJsonValidationErrors('entite');
        $this->enTantQue($admin)->getJson(self::URL.'?du=pas-une-date')->assertUnprocessable()->assertJsonValidationErrors('du');
        $this->enTantQue($admin)->getJson(self::URL.'?du=2026-06-01&au=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->enTantQue($admin)->getJson(self::URL.'?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_l_historique_est_en_lecture_seule(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson(self::URL, [])->assertStatus(405);
        $this->enTantQue($admin)->putJson(self::URL, [])->assertStatus(405);
        $this->enTantQue($admin)->deleteJson(self::URL)->assertStatus(405);
    }
}
