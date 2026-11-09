<?php

namespace Tests\Feature\Admin;

use App\Enums\Statut;
use App\Models\AnneeUniversitaire;
use App\Models\Ec;
use App\Models\HistoriqueModification;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\MaquetteColonne;
use App\Models\Parcours;
use App\Models\Syllabus;
use App\Models\UniteEnseignement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeContenu;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class MaquetteAdminTest extends TestCase
{
    use CreeContenu, CreeDonnees, RefreshDatabase;

    private function url(Maquette $maquette, string $suffixe = ''): string
    {
        return "/api/admin/maquettes/{$maquette->public_id}{$suffixe}";
    }

    // ---------------------------------------------------------------- Droits

    public function test_seuls_les_administrateurs_gerent_les_maquettes(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $prof = $this->enseignant();
        $corps = [
            'niveau' => $d['niveau']->public_id,
            'parcours' => $d['parcours']->public_id,
            'annee' => $d['annee']->public_id,
        ];

        $this->postJson('/api/admin/maquettes', $corps)->assertUnauthorized();
        $this->postJson($this->url($d['maquette'], '/publier'))->assertUnauthorized();

        $this->enTantQue($prof)->postJson('/api/admin/maquettes', $corps)->assertForbidden();
        $this->enTantQue($prof)->putJson($this->url($d['maquette']), ['titre' => 'Piraté'])->assertForbidden();
        $this->enTantQue($prof)->postJson($this->url($d['maquette'], '/publier'))->assertForbidden();
        $this->enTantQue($prof)->postJson($this->url($d['maquette'], '/archiver'))->assertForbidden();
        $this->enTantQue($prof)->postJson($this->url($d['maquette'], '/dupliquer'), ['annee' => $d['annee']->public_id])->assertForbidden();
        $this->enTantQue($prof)->deleteJson($this->url($d['maquette']))->assertForbidden();
        $this->enTantQue($prof)->putJson($this->url($d['maquette'], '/colonnes'), ['colonnes' => []])->assertForbidden();
        $this->enTantQue($prof)->postJson($this->url($d['maquette'], '/unites'), [])->assertForbidden();
        $this->enTantQue($prof)->putJson("/api/admin/unites/{$d['ue']->public_id}", [])->assertForbidden();
        $this->enTantQue($prof)->postJson("/api/admin/unites/{$d['ue']->public_id}/lignes", [])->assertForbidden();
        $this->enTantQue($prof)->putJson("/api/admin/lignes/{$d['ligne']->public_id}", [])->assertForbidden();
        $this->enTantQue($prof)->deleteJson("/api/admin/lignes/{$d['ligne']->public_id}")->assertForbidden();

        $this->assertSame(Statut::Brouillon, $d['maquette']->fresh()->statut);
        $this->assertSame(1, Maquette::count());
        $this->assertDatabaseCount('historique_modifications', 0);
    }

    // ---------------------------------------------------------------- Création

    public function test_creation_d_une_maquette_en_brouillon_avec_numero_de_version(): void
    {
        $d = $this->creerDonnees(); // la version 1 existe déjà (publiée)
        $admin = $this->administrateur();
        $corps = [
            'niveau' => $d['niveau']->public_id,
            'parcours' => $d['parcours']->public_id,
            'annee' => $d['annee']->public_id,
            'titre' => 'Nouvelle version',
        ];

        $this->enTantQue($admin)->postJson('/api/admin/maquettes', $corps)
            ->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.titre', 'Nouvelle version')
            ->assertJsonPath('data.niveau.code', 'L2')
            ->assertJsonPath('data.annee_universitaire.libelle', '2026-2027')
            ->assertJsonCount(0, 'data.semestres');

        $this->enTantQue($admin)->postJson('/api/admin/maquettes', $corps)
            ->assertCreated()->assertJsonPath('data.version', 3);

        $this->assertDatabaseHas('maquettes', ['version' => 2, 'statut' => 'brouillon', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'creation', 'entite_type' => 'maquette']);
        // La maquette publiée n'a pas bougé
        $this->assertSame(Statut::Publie, $d['maquette']->fresh()->statut);
    }

    public function test_la_creation_refuse_les_combinaisons_invalides(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $autre = Parcours::create(['code' => 'P-AUTRE', 'nom' => 'Autre parcours']); // non rattaché au niveau L2

        $this->enTantQue($admin)->postJson('/api/admin/maquettes', [
            'niveau' => $d['niveau']->public_id, 'parcours' => $autre->public_id, 'annee' => $d['annee']->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parcours');

        $d['parcours']->update(['is_active' => false]);
        $this->enTantQue($admin)->postJson('/api/admin/maquettes', [
            'niveau' => $d['niveau']->public_id, 'parcours' => $d['parcours']->public_id, 'annee' => $d['annee']->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parcours');

        $this->enTantQue($admin)->postJson('/api/admin/maquettes', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['niveau', 'parcours', 'annee']);

        $this->enTantQue($admin)->postJson('/api/admin/maquettes', [
            'niveau' => str_repeat('0', 26), 'parcours' => 'abc', 'annee' => str_repeat('1', 26),
        ])->assertUnprocessable()->assertJsonValidationErrors(['niveau', 'parcours', 'annee']);

        $this->assertSame(1, Maquette::count());
    }

    // ---------------------------------------------------------------- Modification

    public function test_on_modifie_le_titre_et_les_notes_d_un_brouillon(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())
            ->putJson($this->url($d['maquette']), ['titre' => 'Titre modifié', 'notes' => 'Une note'])
            ->assertOk()
            ->assertJsonPath('data.titre', 'Titre modifié');

        $this->assertSame('Une note', $d['maquette']->fresh()->notes);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'modification', 'entite_type' => 'maquette']);
    }

    public function test_une_maquette_publiee_est_en_lecture_seule(): void
    {
        $d = $this->creerDonnees(Statut::Publie);

        $this->enTantQue($this->administrateur())
            ->putJson($this->url($d['maquette']), ['titre' => 'Modifié'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette maquette est publiée ou archivée : elle est en lecture seule. Dupliquez-la pour créer une nouvelle version.');

        $this->assertSame('Maquette de test', $d['maquette']->fresh()->titre);
    }

    public function test_le_niveau_le_parcours_et_l_annee_ne_se_modifient_pas(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())->putJson($this->url($d['maquette']), [
            'titre' => 'X', 'niveau' => $d['niveau']->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('niveau');
    }

    // ---------------------------------------------------------------- Publication

    public function test_publication_d_un_brouillon_complet(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson($this->url($d['maquette'], '/publier'))
            ->assertOk()
            ->assertJsonPath('data.statut', 'publie')
            ->assertJsonPath('data.semestres.0.unites.0.code', 'UE1');

        $maquette = $d['maquette']->fresh();
        $this->assertSame(Statut::Publie, $maquette->statut);
        $this->assertNotNull($maquette->published_at);
        $this->assertSame($admin->id, $maquette->published_by);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'publication', 'entite_id' => $maquette->id]);

        // Elle devient visible du public
        $this->enTantQueVisiteur()->getJson("/api/maquettes/{$d['maquette']->public_id}")->assertOk();
    }

    public function test_une_maquette_vide_ne_peut_pas_etre_publiee(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $vide = $this->creerMaquetteSupplementaire($d, Statut::Brouillon, 2);

        $this->enTantQue($this->administrateur())->postJson($this->url($vide, '/publier'))
            ->assertUnprocessable()->assertJsonValidationErrors('maquette');

        $this->assertSame(Statut::Brouillon, $vide->fresh()->statut);
    }

    public function test_une_ue_sans_ec_bloque_la_publication(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $brouillon = $this->creerMaquetteSupplementaire($d, Statut::Brouillon, 2);
        $this->ajouterContenu($brouillon, $d['semestre']);
        UniteEnseignement::create([
            'maquette_id' => $brouillon->id, 'semestre_id' => $d['semestre']->id,
            'code' => 'UE-VIDE', 'intitule' => 'UE sans EC', 'ordre' => 2,
        ]);

        $reponse = $this->enTantQue($this->administrateur())->postJson($this->url($brouillon, '/publier'))
            ->assertUnprocessable()->assertJsonValidationErrors('maquette');

        $this->assertStringContainsString('UE-VIDE', implode(' ', $reponse->json('errors.maquette')));
    }

    public function test_seule_une_maquette_en_brouillon_peut_etre_publiee(): void
    {
        $d = $this->creerDonnees(Statut::Publie);

        $this->enTantQue($this->administrateur())->postJson($this->url($d['maquette'], '/publier'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Seule une maquette en brouillon peut être publiée.');
    }

    public function test_une_version_publiee_n_est_jamais_remplacee_sans_confirmation(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $v2 = $this->creerMaquetteSupplementaire($d, Statut::Brouillon, 2);
        $this->ajouterContenu($v2, $d['semestre']);
        $admin = $this->administrateur();

        $reponse = $this->enTantQue($admin)->postJson($this->url($v2, '/publier'))
            ->assertStatus(409)
            ->assertJsonPath('version_publiee.id', $d['maquette']->public_id)
            ->assertJsonPath('version_publiee.version', 1);
        $this->assertStringContainsString('v1', $reponse->json('message'));

        // Rien n'a changé
        $this->assertSame(Statut::Publie, $d['maquette']->fresh()->statut);
        $this->assertSame(Statut::Brouillon, $v2->fresh()->statut);

        // Avec confirmation explicite : l'ancienne version est archivée, pas supprimée
        $this->enTantQue($admin)->postJson($this->url($v2, '/publier'), ['confirmer_remplacement' => true])
            ->assertOk()->assertJsonPath('data.statut', 'publie')->assertJsonPath('data.version', 2);

        $this->assertSame(Statut::Archive, $d['maquette']->fresh()->statut);
        $this->assertSame(Statut::Publie, $v2->fresh()->statut);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'archivage', 'entite_id' => $d['maquette']->id]);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'publication', 'entite_id' => $v2->id]);
        $this->assertSame(1, Maquette::publiees()->count());
    }

    public function test_la_confirmation_doit_etre_un_booleen(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())
            ->postJson($this->url($d['maquette'], '/publier'), ['confirmer_remplacement' => 'peut-etre'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmer_remplacement');
    }

    // ---------------------------------------------------------------- Archivage

    public function test_archivage_d_une_maquette_publiee(): void
    {
        $d = $this->creerDonnees(Statut::Publie);

        $this->enTantQue($this->administrateur())->postJson($this->url($d['maquette'], '/archiver'))
            ->assertOk()->assertJsonPath('data.statut', 'archive');

        $this->assertDatabaseHas('historique_modifications', ['action' => 'archivage', 'entite_id' => $d['maquette']->id]);

        // Une maquette archivée n'est plus visible du public
        $this->enTantQueVisiteur()->getJson("/api/maquettes/{$d['maquette']->public_id}")->assertNotFound();
    }

    public function test_on_n_archive_pas_un_brouillon(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())->postJson($this->url($d['maquette'], '/archiver'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Seule une maquette publiée peut être archivée.');
    }

    // ---------------------------------------------------------------- Suppression

    public function test_suppression_d_un_brouillon_et_de_son_contenu(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        MaquetteColonne::create(['maquette_id' => $d['maquette']->id, 'cle' => 'observation', 'libelle' => 'Observation', 'ordre' => 0]);

        $this->enTantQue($this->administrateur())->deleteJson($this->url($d['maquette']))->assertNoContent();

        $this->assertDatabaseCount('maquettes', 0);
        $this->assertDatabaseCount('unites_enseignement', 0);
        $this->assertDatabaseCount('ue_ec', 0);
        $this->assertDatabaseCount('maquette_colonnes', 0);
        // Le catalogue d'EC et les syllabus ne sont pas touchés
        $this->assertSame(1, Ec::count());
        $this->assertSame(1, Syllabus::count());

        $entree = HistoriqueModification::where('action', 'suppression')->first();
        $this->assertNotNull($entree);
        $this->assertSame(1, $entree->anciennes_valeurs['unites']);
        $this->assertSame(1, $entree->anciennes_valeurs['lignes']);
    }

    public function test_une_maquette_publiee_ou_archivee_ne_se_supprime_pas(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $archivee = $this->creerMaquetteSupplementaire($d, Statut::Archive, 2);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->deleteJson($this->url($d['maquette']))
            ->assertStatus(409)
            ->assertJsonPath('message', "Seule une maquette en brouillon peut être supprimée : une maquette publiée s'archive.");
        $this->enTantQue($admin)->deleteJson($this->url($archivee))->assertStatus(409);

        $this->assertSame(2, Maquette::count());
    }

    // ---------------------------------------------------------------- Duplication

    public function test_duplication_vers_une_nouvelle_annee(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        MaquetteColonne::create(['maquette_id' => $d['maquette']->id, 'cle' => 'observation', 'libelle' => 'Observation', 'ordre' => 0]);
        $nouvelle = AnneeUniversitaire::create(['libelle' => '2027-2028']);

        $reponse = $this->enTantQue($this->administrateur())
            ->postJson($this->url($d['maquette'], '/dupliquer'), ['annee' => $nouvelle->public_id])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.annee_universitaire.libelle', '2027-2028')
            ->assertJsonPath('data.colonnes.0.cle', 'observation')
            ->assertJsonPath('data.semestres.0.unites.0.code', 'UE1')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.ec.code', 'EC1')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.heures_total', '40.0')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.syllabus_id', null);

        $this->assertNotSame($d['maquette']->public_id, $reponse->json('data.id'));
        $this->assertNotSame($d['ue']->public_id, $reponse->json('data.semestres.0.unites.0.id'));

        // L'originale est intacte et la copie est indépendante
        $this->assertSame(2, Maquette::count());
        $this->assertSame(2, UniteEnseignement::count());
        $this->assertSame(2, LigneMaquette::count());
        $this->assertSame(Statut::Publie, $d['maquette']->fresh()->statut);
        // Le catalogue d'EC est partagé, les syllabus ne sont pas copiés
        $this->assertSame(1, Ec::count());
        $this->assertSame(1, Syllabus::count());
        $this->assertDatabaseHas('historique_modifications', ['action' => 'duplication', 'entite_type' => 'maquette']);
    }

    public function test_duplication_dans_la_meme_annee_cree_une_nouvelle_version(): void
    {
        $d = $this->creerDonnees(Statut::Publie);

        $this->enTantQue($this->administrateur())
            ->postJson($this->url($d['maquette'], '/dupliquer'), ['annee' => $d['annee']->public_id, 'titre' => 'Version corrigée'])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.titre', 'Version corrigée')
            ->assertJsonPath('data.statut', 'brouillon');
    }

    public function test_une_maquette_archivee_peut_etre_dupliquee(): void
    {
        $d = $this->creerDonnees(Statut::Archive);

        $this->enTantQue($this->administrateur())
            ->postJson($this->url($d['maquette'], '/dupliquer'), ['annee' => $d['annee']->public_id])
            ->assertCreated()->assertJsonPath('data.statut', 'brouillon');
    }

    public function test_la_duplication_exige_une_annee_valide(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson($this->url($d['maquette'], '/dupliquer'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('annee');
        $this->enTantQue($admin)->postJson($this->url($d['maquette'], '/dupliquer'), ['annee' => str_repeat('0', 26)])
            ->assertUnprocessable()->assertJsonValidationErrors('annee');

        $this->assertSame(1, Maquette::count());
    }

    public function test_une_maquette_inconnue_donne_404(): void
    {
        $admin = $this->administrateur();
        $inconnue = '/api/admin/maquettes/'.str_repeat('0', 26);

        $this->enTantQue($admin)->putJson($inconnue, ['titre' => 'X'])->assertNotFound();
        $this->enTantQue($admin)->postJson($inconnue.'/publier')->assertNotFound();
        $this->enTantQue($admin)->deleteJson($inconnue)->assertNotFound();
    }
}
