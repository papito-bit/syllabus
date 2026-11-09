<?php

namespace Tests\Feature\Admin;

use App\Enums\Statut;
use App\Models\Ec;
use App\Models\HistoriqueModification;
use App\Models\LigneMaquette;
use App\Models\MaquetteColonne;
use App\Models\Semestre;
use App\Models\UniteEnseignement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeContenu;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

/** Contenu d'une maquette en brouillon : UE, lignes d'EC et colonnes supplémentaires. */
class ContenuMaquetteAdminTest extends TestCase
{
    use CreeContenu, CreeDonnees, RefreshDatabase;

    private const LECTURE_SEULE = 'Cette maquette est publiée ou archivée : elle est en lecture seule. Dupliquez-la pour créer une nouvelle version.';

    private function urlUnites(array $d): string
    {
        return "/api/admin/maquettes/{$d['maquette']->public_id}/unites";
    }

    private function colonne(array $d, string $cle = 'observation'): MaquetteColonne
    {
        return MaquetteColonne::create([
            'maquette_id' => $d['maquette']->id, 'cle' => $cle, 'libelle' => ucfirst($cle), 'ordre' => 0,
        ]);
    }

    // ---------------------------------------------------------------- UE

    public function test_ajout_d_une_ue_dans_un_brouillon(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())->postJson($this->urlUnites($d), [
            'semestre' => $d['semestre']->public_id,
            'code' => 'ue2',
            'intitule' => 'Deuxième UE',
            'ordre' => 2,
            'credits' => 4,
            'coefficient' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'UE2')
            ->assertJsonPath('data.semestre.code', 'S3')
            ->assertJsonPath('data.credits', '4.0');

        $this->assertDatabaseHas('unites_enseignement', ['maquette_id' => $d['maquette']->id, 'code' => 'UE2']);
        // Le journal est rattaché à la maquette
        $this->assertDatabaseHas('historique_modifications', ['action' => 'ajout_ue', 'entite_id' => $d['maquette']->id]);
    }

    public function test_un_code_d_ue_est_unique_par_semestre_dans_une_maquette(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon); // UE1 existe en S3
        $autreSemestre = Semestre::create(['code' => 'S4', 'libelle' => 'Semestre 4', 'ordre' => 4]);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson($this->urlUnites($d), [
            'semestre' => $d['semestre']->public_id, 'code' => 'UE1', 'intitule' => 'Doublon',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        // Le même code est permis dans un autre semestre
        $this->enTantQue($admin)->postJson($this->urlUnites($d), [
            'semestre' => $autreSemestre->public_id, 'code' => 'UE1', 'intitule' => 'Autre semestre',
        ])->assertCreated();
    }

    public function test_les_donnees_d_une_ue_sont_validees(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $admin = $this->administrateur();
        $url = $this->urlUnites($d);

        $this->enTantQue($admin)->postJson($url, [])
            ->assertUnprocessable()->assertJsonValidationErrors(['semestre', 'code', 'intitule']);
        $this->enTantQue($admin)->postJson($url, ['semestre' => str_repeat('0', 26), 'code' => 'UE2', 'intitule' => 'X'])
            ->assertUnprocessable()->assertJsonValidationErrors('semestre');
        $this->enTantQue($admin)->postJson($url, ['semestre' => $d['semestre']->public_id, 'code' => 'UE 2', 'intitule' => 'X'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->enTantQue($admin)->postJson($url, ['semestre' => $d['semestre']->public_id, 'code' => 'UE2', 'intitule' => 'X', 'credits' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('credits');
        $this->enTantQue($admin)->postJson($url, ['semestre' => $d['semestre']->public_id, 'code' => 'UE2', 'intitule' => 'X', 'credits' => 4.55])
            ->assertUnprocessable()->assertJsonValidationErrors('credits');

        $this->assertSame(1, UniteEnseignement::count());
    }

    public function test_on_ne_modifie_pas_le_contenu_d_une_maquette_publiee(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $admin = $this->administrateur();
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);

        $this->enTantQue($admin)->postJson($this->urlUnites($d), [
            'semestre' => $d['semestre']->public_id, 'code' => 'UE2', 'intitule' => 'X',
        ])->assertStatus(409)->assertJsonPath('message', self::LECTURE_SEULE);

        $this->enTantQue($admin)->putJson("/api/admin/unites/{$d['ue']->public_id}", ['code' => 'UE1', 'intitule' => 'Modifié'])
            ->assertStatus(409);
        $this->enTantQue($admin)->deleteJson("/api/admin/unites/{$d['ue']->public_id}")->assertStatus(409);
        $this->enTantQue($admin)->postJson("/api/admin/unites/{$d['ue']->public_id}/lignes", ['ec' => $ec->public_id])
            ->assertStatus(409);
        $this->enTantQue($admin)->putJson("/api/admin/lignes/{$d['ligne']->public_id}", ['heures_cm' => 99])
            ->assertStatus(409);
        $this->enTantQue($admin)->deleteJson("/api/admin/lignes/{$d['ligne']->public_id}")->assertStatus(409);
        $this->enTantQue($admin)->putJson("/api/admin/maquettes/{$d['maquette']->public_id}/colonnes", ['colonnes' => []])
            ->assertStatus(409);

        $this->assertSame(1, UniteEnseignement::count());
        $this->assertSame('UE de test', $d['ue']->fresh()->intitule);
        $this->assertSame(1, LigneMaquette::count());
        $this->assertDatabaseCount('historique_modifications', 0);
    }

    public function test_modification_d_une_ue_journalisee_sur_la_maquette(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())
            ->putJson("/api/admin/unites/{$d['ue']->public_id}", ['code' => 'UE1', 'intitule' => 'Intitulé modifié', 'ordre' => 1])
            ->assertOk()
            ->assertJsonPath('data.intitule', 'Intitulé modifié');

        $entree = HistoriqueModification::where('action', 'modification_ue')->first();
        $this->assertNotNull($entree);
        $this->assertSame($d['maquette']->id, $entree->entite_id);
        $this->assertSame('UE1', $entree->anciennes_valeurs['ue']);
        $this->assertSame('UE de test', $entree->anciennes_valeurs['intitule']);
        $this->assertSame('Intitulé modifié', $entree->nouvelles_valeurs['intitule']);
    }

    public function test_une_ue_peut_changer_de_semestre_mais_pas_creer_de_doublon(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $s4 = Semestre::create(['code' => 'S4', 'libelle' => 'Semestre 4', 'ordre' => 4]);
        $admin = $this->administrateur();

        $this->enTantQue($admin)
            ->putJson("/api/admin/unites/{$d['ue']->public_id}", ['semestre' => $s4->public_id, 'code' => 'UE1', 'intitule' => 'UE de test'])
            ->assertOk()->assertJsonPath('data.semestre.code', 'S4');

        // Une seconde UE "UE1" en S3 est maintenant possible, mais on ne peut pas la ramener en S4
        $deuxieme = $this->enTantQue($admin)->postJson($this->urlUnites($d), [
            'semestre' => $d['semestre']->public_id, 'code' => 'UE1', 'intitule' => 'Seconde',
        ])->assertCreated()->json('data.id');

        $this->enTantQue($admin)
            ->putJson("/api/admin/unites/{$deuxieme}", ['semestre' => $s4->public_id, 'code' => 'UE1', 'intitule' => 'Seconde'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_suppression_d_une_ue_et_de_ses_lignes(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())->deleteJson("/api/admin/unites/{$d['ue']->public_id}")->assertNoContent();

        $this->assertDatabaseCount('unites_enseignement', 0);
        $this->assertDatabaseCount('ue_ec', 0);
        $this->assertSame(1, Ec::count()); // le catalogue n'est pas touché

        $entree = HistoriqueModification::where('action', 'suppression_ue')->first();
        $this->assertNotNull($entree);
        $this->assertSame($d['maquette']->id, $entree->entite_id);
        $this->assertSame(1, $entree->anciennes_valeurs['lignes']);
    }

    // ---------------------------------------------------------------- Lignes (EC)

    public function test_ajout_d_un_ec_calcule_le_volume_horaire_total(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);

        $this->enTantQue($this->administrateur())->postJson("/api/admin/unites/{$d['ue']->public_id}/lignes", [
            'ec' => $ec->public_id,
            'heures_cm' => 10, 'heures_td' => 5, 'heures_tp' => 5,
            'credits' => 3, 'coefficient' => 1.5,
            'modalite_evaluation' => 'Examen',
        ])->assertCreated()
            ->assertJsonPath('data.ec.code', 'EC2')
            ->assertJsonPath('data.heures_total', '20.0')
            ->assertJsonPath('data.modalite_evaluation', 'Examen');

        $this->assertSame(2, LigneMaquette::count());
        $this->assertDatabaseHas('historique_modifications', ['action' => 'ajout_ec', 'entite_id' => $d['maquette']->id]);
    }

    public function test_un_volume_horaire_incoherent_est_refuse(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);
        $url = "/api/admin/unites/{$d['ue']->public_id}/lignes";
        $admin = $this->administrateur();

        $reponse = $this->enTantQue($admin)->postJson($url, [
            'ec' => $ec->public_id, 'heures_cm' => 10, 'heures_td' => 5, 'heures_tp' => 5, 'heures_total' => 30,
        ])->assertUnprocessable()->assertJsonValidationErrors('heures_total');
        $this->assertStringContainsString('20', implode(' ', $reponse->json('errors.heures_total')));

        // Un total cohérent est accepté
        $this->enTantQue($admin)->postJson($url, [
            'ec' => $ec->public_id, 'heures_cm' => 10, 'heures_td' => 5, 'heures_tp' => 5, 'heures_total' => 20,
        ])->assertCreated()->assertJsonPath('data.heures_total', '20.0');
    }

    public function test_les_donnees_d_une_ligne_sont_validees(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);
        $url = "/api/admin/unites/{$d['ue']->public_id}/lignes";
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('ec');
        $this->enTantQue($admin)->postJson($url, ['ec' => str_repeat('0', 26)])->assertUnprocessable()->assertJsonValidationErrors('ec');
        $this->enTantQue($admin)->postJson($url, ['ec' => $ec->public_id, 'heures_cm' => -5])->assertUnprocessable()->assertJsonValidationErrors('heures_cm');
        $this->enTantQue($admin)->postJson($url, ['ec' => $ec->public_id, 'heures_cm' => 'beaucoup'])->assertUnprocessable()->assertJsonValidationErrors('heures_cm');
        $this->enTantQue($admin)->postJson($url, ['ec' => $ec->public_id, 'coefficient' => 1000])->assertUnprocessable()->assertJsonValidationErrors('coefficient');

        // Le même EC ne peut pas figurer deux fois dans la même UE
        $this->enTantQue($admin)->postJson($url, ['ec' => $d['ec']->public_id])
            ->assertUnprocessable()->assertJsonValidationErrors('ec');

        $this->assertSame(1, LigneMaquette::count());
    }

    public function test_les_colonnes_supplementaires_d_une_ligne_doivent_etre_declarees(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);
        $url = "/api/admin/unites/{$d['ue']->public_id}/lignes";
        $admin = $this->administrateur();
        $this->colonne($d, 'observation');

        $this->enTantQue($admin)->postJson($url, ['ec' => $ec->public_id, 'donnees_supplementaires' => ['inconnue' => 'x']])
            ->assertUnprocessable()->assertJsonValidationErrors('donnees_supplementaires');

        $this->enTantQue($admin)->postJson($url, ['ec' => $ec->public_id, 'donnees_supplementaires' => ['observation' => 'Texte']])
            ->assertCreated()->assertJsonPath('data.donnees_supplementaires.observation', 'Texte');
    }

    public function test_modification_d_une_ligne_recalcule_le_total_si_besoin(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon); // CM 20, TD 10, TP 10, total 40
        $admin = $this->administrateur();
        $url = "/api/admin/lignes/{$d['ligne']->public_id}";

        $this->enTantQue($admin)->putJson($url, ['heures_cm' => 30])
            ->assertOk()
            ->assertJsonPath('data.heures_cm', '30.0')
            ->assertJsonPath('data.heures_total', '50.0');

        $entree = HistoriqueModification::where('action', 'modification_ec')->first();
        $this->assertNotNull($entree);
        $this->assertSame($d['maquette']->id, $entree->entite_id);
        $this->assertSame('EC1', $entree->nouvelles_valeurs['ec']);

        // Modifier un champ sans lien avec les heures laisse le total intact
        $this->enTantQue($admin)->putJson($url, ['modalite_evaluation' => 'Contrôle continu'])
            ->assertOk()
            ->assertJsonPath('data.modalite_evaluation', 'Contrôle continu')
            ->assertJsonPath('data.heures_total', '50.0');
    }

    public function test_l_ec_d_une_ligne_ne_se_change_pas(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $autre = Ec::create(['code' => 'EC2', 'intitule' => 'EC 2']);

        $this->enTantQue($this->administrateur())
            ->putJson("/api/admin/lignes/{$d['ligne']->public_id}", ['ec' => $autre->public_id])
            ->assertUnprocessable()->assertJsonValidationErrors('ec');

        $this->assertSame($d['ec']->id, $d['ligne']->fresh()->ec_id);
    }

    public function test_retrait_d_un_ec_d_une_ue(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())->deleteJson("/api/admin/lignes/{$d['ligne']->public_id}")->assertNoContent();

        $this->assertDatabaseCount('ue_ec', 0);
        $this->assertSame(1, Ec::count());
        $this->assertSame(1, UniteEnseignement::count());
        $this->assertDatabaseHas('historique_modifications', ['action' => 'retrait_ec', 'entite_id' => $d['maquette']->id]);
    }

    // ---------------------------------------------------------------- Colonnes supplémentaires

    public function test_definition_des_colonnes_supplementaires(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $url = "/api/admin/maquettes/{$d['maquette']->public_id}/colonnes";

        $this->enTantQue($this->administrateur())->putJson($url, ['colonnes' => [
            ['cle' => 'observation', 'libelle' => 'Observation'],
            ['cle' => 'langue', 'libelle' => "Langue d'enseignement"],
        ]])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cle', 'observation')
            ->assertJsonPath('data.0.ordre', 0)
            ->assertJsonPath('data.1.cle', 'langue')
            ->assertJsonPath('data.1.libelle', "Langue d'enseignement");

        $this->assertSame(2, MaquetteColonne::count());
        $this->assertDatabaseHas('historique_modifications', ['action' => 'modification_colonnes', 'entite_id' => $d['maquette']->id]);
    }

    public function test_renvoyer_la_meme_liste_n_est_pas_journalise_deux_fois(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $url = "/api/admin/maquettes/{$d['maquette']->public_id}/colonnes";
        $corps = ['colonnes' => [['cle' => 'observation', 'libelle' => 'Observation']]];
        $admin = $this->administrateur();

        $this->enTantQue($admin)->putJson($url, $corps)->assertOk();
        $this->enTantQue($admin)->putJson($url, $corps)->assertOk();

        $this->assertSame(1, HistoriqueModification::where('action', 'modification_colonnes')->count());
        $this->assertSame(1, MaquetteColonne::count());
    }

    public function test_les_colonnes_invalides_sont_refusees(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $url = "/api/admin/maquettes/{$d['maquette']->public_id}/colonnes";
        $admin = $this->administrateur();

        // Clé réservée à une colonne standard
        $this->enTantQue($admin)->putJson($url, ['colonnes' => [['cle' => 'credits', 'libelle' => 'Crédits']]])
            ->assertUnprocessable()->assertJsonValidationErrors('colonnes.0.cle');
        // Format de clé invalide
        $this->enTantQue($admin)->putJson($url, ['colonnes' => [['cle' => 'Mauvaise Clé', 'libelle' => 'X']]])
            ->assertUnprocessable()->assertJsonValidationErrors('colonnes.0.cle');
        // Titre manquant
        $this->enTantQue($admin)->putJson($url, ['colonnes' => [['cle' => 'observation']]])
            ->assertUnprocessable()->assertJsonValidationErrors('colonnes.0.libelle');
        // Doublon
        $this->enTantQue($admin)->putJson($url, ['colonnes' => [
            ['cle' => 'observation', 'libelle' => 'A'], ['cle' => 'observation', 'libelle' => 'B'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('colonnes.0.cle');
        // Liste absente
        $this->enTantQue($admin)->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('colonnes');
        // Trop de colonnes
        $trop = array_map(fn ($i) => ['cle' => 'col_'.$i, 'libelle' => 'Colonne '.$i], range(1, 16));
        $this->enTantQue($admin)->putJson($url, ['colonnes' => $trop])->assertUnprocessable()->assertJsonValidationErrors('colonnes');

        $this->assertSame(0, MaquetteColonne::count());
    }

    public function test_retirer_une_colonne_supprime_ses_valeurs(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);
        $this->colonne($d, 'observation');
        $this->colonne($d, 'langue');
        $d['ligne']->update(['donnees_supplementaires' => ['observation' => 'À supprimer', 'langue' => 'Français']]);
        $d['ue']->update(['donnees_supplementaires' => ['observation' => 'Aussi à supprimer']]);

        $this->enTantQue($this->administrateur())
            ->putJson("/api/admin/maquettes/{$d['maquette']->public_id}/colonnes", ['colonnes' => [
                ['cle' => 'langue', 'libelle' => 'Langue'],
            ]])->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(['langue' => 'Français'], $d['ligne']->fresh()->donnees_supplementaires);
        $this->assertNull($d['ue']->fresh()->donnees_supplementaires);
        $this->assertSame(['langue'], MaquetteColonne::pluck('cle')->all());
    }
}
