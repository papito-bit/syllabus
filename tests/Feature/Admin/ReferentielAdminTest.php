<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeUniversitaire;
use App\Models\Niveau;
use App\Models\Parcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class ReferentielAdminTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    public function test_les_routes_d_administration_exigent_un_administrateur(): void
    {
        $urls = [
            '/api/admin/niveaux', '/api/admin/parcours', '/api/admin/annees', '/api/admin/semestres',
            '/api/admin/ecs', '/api/admin/utilisateurs', '/api/admin/historique',
        ];
        $prof = $this->enseignant();

        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        foreach ($urls as $url) {
            $this->enTantQue($prof)->getJson($url)->assertForbidden();
        }
    }

    public function test_un_enseignant_ne_peut_rien_ecrire_dans_le_referentiel(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();

        $this->enTantQue($prof)->postJson('/api/admin/niveaux', ['code' => 'M1', 'libelle' => 'Master 1'])->assertForbidden();
        $this->enTantQue($prof)->putJson("/api/admin/niveaux/{$d['niveau']->public_id}", ['code' => 'L2', 'libelle' => 'Piraté'])->assertForbidden();
        $this->enTantQue($prof)->deleteJson("/api/admin/niveaux/{$d['niveau']->public_id}")->assertForbidden();
        $this->enTantQue($prof)->postJson('/api/admin/ecs', ['code' => 'X1', 'intitule' => 'Piraté'])->assertForbidden();
        $this->enTantQue($prof)->postJson('/api/admin/semestres', ['code' => 'S9', 'libelle' => 'Piraté'])->assertForbidden();

        $this->assertSame(1, Niveau::count());
        $this->assertSame('Licence 2', $d['niveau']->fresh()->libelle);
        $this->assertDatabaseCount('ecs', 1);
        $this->assertDatabaseCount('historique_modifications', 0);
    }

    // ---------------------------------------------------------------- Niveaux

    public function test_creation_d_un_niveau_normalise_le_code_et_est_journalisee(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)
            ->postJson('/api/admin/niveaux', ['code' => ' m1 ', 'libelle' => 'Master 1', 'ordre' => 4])
            ->assertCreated()
            ->assertJsonPath('data.code', 'M1')
            ->assertJsonPath('data.libelle', 'Master 1')
            ->assertJsonPath('data.ordre', 4);

        $this->assertDatabaseHas('niveaux', ['code' => 'M1']);
        $this->assertDatabaseHas('historique_modifications', [
            'action' => 'creation', 'entite_type' => 'niveau', 'user_id' => $admin->id,
        ]);
    }

    public function test_un_code_de_niveau_en_double_ou_invalide_est_refuse(): void
    {
        $this->creerDonnees(); // crée déjà L2
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson('/api/admin/niveaux', ['code' => 'L2', 'libelle' => 'Doublon'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->enTantQue($admin)->postJson('/api/admin/niveaux', ['code' => 'M 1', 'libelle' => 'Espace interdit'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->enTantQue($admin)->postJson('/api/admin/niveaux', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'libelle']);
    }

    public function test_modification_d_un_niveau_journalise_ancienne_et_nouvelle_valeur(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)
            ->putJson("/api/admin/niveaux/{$d['niveau']->public_id}", ['code' => 'L2', 'libelle' => 'Licence 2 (modifié)', 'ordre' => 2])
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Licence 2 (modifié)')
            ->assertJsonPath('data.maquettes_count', 1)
            ->assertJsonPath('data.parcours_count', 1);

        $entree = \App\Models\HistoriqueModification::where('action', 'modification')->first();
        $this->assertNotNull($entree);
        $this->assertSame('Licence 2', $entree->anciennes_valeurs['libelle']);
        $this->assertSame('Licence 2 (modifié)', $entree->nouvelles_valeurs['libelle']);
    }

    public function test_une_modification_identique_n_est_pas_journalisee(): void
    {
        $d = $this->creerDonnees();

        $this->enTantQue($this->administrateur())
            ->putJson("/api/admin/niveaux/{$d['niveau']->public_id}", ['code' => 'L2', 'libelle' => 'Licence 2', 'ordre' => 2])
            ->assertOk();

        $this->assertDatabaseCount('historique_modifications', 0);
    }

    public function test_un_niveau_avec_des_maquettes_ne_peut_pas_etre_supprime(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->deleteJson("/api/admin/niveaux/{$d['niveau']->public_id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ce niveau possède des maquettes : il ne peut pas être supprimé.');

        $this->assertDatabaseHas('niveaux', ['code' => 'L2']);
    }

    public function test_un_niveau_inutilise_peut_etre_supprime_et_la_suppression_est_journalisee(): void
    {
        $admin = $this->administrateur();
        $id = $this->enTantQue($admin)->postJson('/api/admin/niveaux', ['code' => 'M2', 'libelle' => 'Master 2'])->json('data.id');

        $this->enTantQue($admin)->deleteJson("/api/admin/niveaux/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('niveaux', ['code' => 'M2']);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'suppression', 'entite_type' => 'niveau']);
    }

    // ---------------------------------------------------------------- Parcours

    public function test_creation_d_un_parcours_rattache_a_un_niveau(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson('/api/admin/parcours', [
            'code' => 'p-new',
            'nom' => 'Nouveau parcours',
            'niveaux' => [$d['niveau']->public_id],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'P-NEW')
            ->assertJsonPath('data.niveaux.0.code', 'L2')
            ->assertJsonPath('data.is_active', true);

        $parcours = Parcours::where('code', 'P-NEW')->first();
        $this->assertDatabaseHas('niveau_parcours', ['niveau_id' => $d['niveau']->id, 'parcours_id' => $parcours->id]);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'creation', 'entite_type' => 'parcours']);
    }

    public function test_un_parcours_exige_au_moins_un_niveau_existant(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson('/api/admin/parcours', ['code' => 'P1', 'nom' => 'Sans niveau'])
            ->assertUnprocessable()->assertJsonValidationErrors('niveaux');
        $this->enTantQue($admin)->postJson('/api/admin/parcours', ['code' => 'P1', 'nom' => 'Niveau inconnu', 'niveaux' => [str_repeat('0', 26)]])
            ->assertUnprocessable()->assertJsonValidationErrors('niveaux.0');
        $this->enTantQue($admin)->postJson('/api/admin/parcours', ['code' => 'P1', 'nom' => 'Niveau mal formé', 'niveaux' => ['abc']])
            ->assertUnprocessable()->assertJsonValidationErrors('niveaux.0');

        $this->assertDatabaseCount('parcours', 0);
    }

    public function test_modification_et_desactivation_d_un_parcours(): void
    {
        $d = $this->creerDonnees();

        $this->enTantQue($this->administrateur())->putJson("/api/admin/parcours/{$d['parcours']->public_id}", [
            'code' => 'P-TEST',
            'nom' => 'Parcours renommé',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.nom', 'Parcours renommé')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.maquettes_count', 1);

        $this->assertFalse((bool) $d['parcours']->fresh()->is_active);
    }

    public function test_on_peut_ajouter_un_niveau_a_un_parcours(): void
    {
        $d = $this->creerDonnees();
        $l3 = Niveau::create(['code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3]);

        $this->enTantQue($this->administrateur())->putJson("/api/admin/parcours/{$d['parcours']->public_id}", [
            'code' => 'P-TEST',
            'nom' => 'Parcours de test',
            'niveaux' => [$d['niveau']->public_id, $l3->public_id],
        ])->assertOk()->assertJsonCount(2, 'data.niveaux');

        $this->assertDatabaseHas('historique_modifications', [
            'action' => 'modification', 'description' => "Modification des niveaux d'un parcours",
        ]);
    }

    public function test_on_ne_peut_pas_retirer_un_niveau_qui_possede_une_maquette(): void
    {
        $d = $this->creerDonnees();
        $l3 = Niveau::create(['code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3]);

        $this->enTantQue($this->administrateur())->putJson("/api/admin/parcours/{$d['parcours']->public_id}", [
            'code' => 'P-TEST',
            'nom' => 'Parcours de test',
            'niveaux' => [$l3->public_id], // retire L2, qui a une maquette
        ])->assertUnprocessable()->assertJsonValidationErrors('niveaux');

        $this->assertDatabaseHas('niveau_parcours', ['niveau_id' => $d['niveau']->id, 'parcours_id' => $d['parcours']->id]);
        $this->assertDatabaseMissing('niveau_parcours', ['niveau_id' => $l3->id, 'parcours_id' => $d['parcours']->id]);
    }

    public function test_un_parcours_avec_des_maquettes_ne_peut_pas_etre_supprime(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->deleteJson("/api/admin/parcours/{$d['parcours']->public_id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ce parcours possède des maquettes : il ne peut pas être supprimé.');

        $id = $this->enTantQue($admin)->postJson('/api/admin/parcours', [
            'code' => 'P-LIBRE', 'nom' => 'Libre', 'niveaux' => [$d['niveau']->public_id],
        ])->json('data.id');
        $this->enTantQue($admin)->deleteJson("/api/admin/parcours/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('parcours', ['code' => 'P-LIBRE']);
    }

    // ---------------------------------------------------------------- Années

    public function test_une_seule_annee_courante_a_la_fois(): void
    {
        $this->creerDonnees(); // 2026-2027 est courante
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson('/api/admin/annees', ['libelle' => '2027-2028', 'is_courante' => true])
            ->assertCreated()
            ->assertJsonPath('data.libelle', '2027-2028')
            ->assertJsonPath('data.is_courante', true);

        $this->assertFalse((bool) AnneeUniversitaire::where('libelle', '2026-2027')->first()->is_courante);
        $this->assertSame(1, AnneeUniversitaire::where('is_courante', true)->count());
    }

    public function test_une_annee_doit_avoir_un_format_et_une_suite_valides(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson('/api/admin/annees', ['libelle' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('libelle');

        $reponse = $this->enTantQue($admin)->postJson('/api/admin/annees', ['libelle' => '2027-2029'])
            ->assertUnprocessable()->assertJsonValidationErrors('libelle');
        $this->assertStringContainsString('suivre', implode(' ', $reponse->json('errors.libelle')));

        $this->enTantQue($admin)->postJson('/api/admin/annees', [
            'libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2027-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }

    public function test_modifier_l_annee_courante_retire_le_statut_aux_autres(): void
    {
        $d = $this->creerDonnees();
        $autre = AnneeUniversitaire::create(['libelle' => '2027-2028', 'is_courante' => false]);

        $this->enTantQue($this->administrateur())
            ->putJson("/api/admin/annees/{$autre->public_id}", ['libelle' => '2027-2028', 'is_courante' => true])
            ->assertOk()->assertJsonPath('data.is_courante', true);

        $this->assertFalse((bool) $d['annee']->fresh()->is_courante);
    }

    public function test_une_annee_utilisee_ne_peut_pas_etre_supprimee(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $libre = AnneeUniversitaire::create(['libelle' => '2030-2031']);

        $this->enTantQue($admin)->deleteJson("/api/admin/annees/{$d['annee']->public_id}")->assertStatus(409);
        $this->enTantQue($admin)->deleteJson("/api/admin/annees/{$libre->public_id}")->assertNoContent();
        $this->assertDatabaseMissing('annees_universitaires', ['libelle' => '2030-2031']);
    }

    // ---------------------------------------------------------------- Semestres

    public function test_creation_modification_et_suppression_d_un_semestre(): void
    {
        $d = $this->creerDonnees(); // crée S3, utilisé par une UE
        $admin = $this->administrateur();

        $id = $this->enTantQue($admin)->postJson('/api/admin/semestres', ['code' => 's11', 'libelle' => 'Semestre 11', 'ordre' => 11])
            ->assertCreated()->assertJsonPath('data.code', 'S11')->json('data.id');

        $this->enTantQue($admin)->putJson("/api/admin/semestres/{$id}", ['code' => 'S11', 'libelle' => 'Onzième semestre', 'ordre' => 11])
            ->assertOk()->assertJsonPath('data.libelle', 'Onzième semestre')->assertJsonPath('data.unites_count', 0);

        $this->enTantQue($admin)->postJson('/api/admin/semestres', ['code' => 'S3', 'libelle' => 'Doublon'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->enTantQue($admin)->postJson('/api/admin/semestres', ['code' => 'S 4', 'libelle' => 'Invalide'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->enTantQue($admin)->deleteJson("/api/admin/semestres/{$d['semestre']->public_id}")->assertStatus(409);
        $this->enTantQue($admin)->deleteJson("/api/admin/semestres/{$id}")->assertNoContent();
    }

    // ---------------------------------------------------------------- EC

    public function test_creation_et_modification_d_un_ec(): void
    {
        $this->creerDonnees(); // crée EC1
        $admin = $this->administrateur();

        $id = $this->enTantQue($admin)->postJson('/api/admin/ecs', ['code' => 'ec-new1', 'intitule' => 'Nouvel EC', 'description' => 'Texte'])
            ->assertCreated()->assertJsonPath('data.code', 'EC-NEW1')->json('data.id');

        $this->enTantQue($admin)->putJson("/api/admin/ecs/{$id}", ['code' => 'EC-NEW1', 'intitule' => 'EC renommé'])
            ->assertOk()->assertJsonPath('data.intitule', 'EC renommé');

        $this->enTantQue($admin)->postJson('/api/admin/ecs', ['code' => 'EC1', 'intitule' => 'Doublon'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_recherche_et_comptage_dans_le_catalogue_des_ec(): void
    {
        $this->creerDonnees(); // EC1 : 1 ligne de maquette, 1 syllabus
        $admin = $this->administrateur();
        $this->enTantQue($admin)->postJson('/api/admin/ecs', ['code' => 'AUTRE', 'intitule' => 'Autre matière']);

        $this->enTantQue($admin)->getJson('/api/admin/ecs')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data', 'links', 'meta']);

        $this->enTantQue($admin)->getJson('/api/admin/ecs?q=EC1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'EC1')
            ->assertJsonPath('data.0.lignes_count', 1)
            ->assertJsonPath('data.0.syllabus_count', 1);

        $this->enTantQue($admin)->getJson('/api/admin/ecs?q=introuvable')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson('/api/admin/ecs?per_page=1000')->assertUnprocessable();
    }

    public function test_un_ec_utilise_ne_peut_pas_etre_supprime(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->deleteJson("/api/admin/ecs/{$d['ec']->public_id}")->assertStatus(409);

        $id = $this->enTantQue($admin)->postJson('/api/admin/ecs', ['code' => 'LIBRE', 'intitule' => 'Libre'])->json('data.id');
        $this->enTantQue($admin)->deleteJson("/api/admin/ecs/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('ecs', ['code' => 'LIBRE']);
    }
}
