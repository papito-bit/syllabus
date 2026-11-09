<?php

namespace Tests\Feature\Admin;

use App\Enums\Statut;
use App\Models\AnneeUniversitaire;
use App\Models\Ec;
use App\Models\Syllabus;
use App\Models\SyllabusAutorisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeContenu;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class SyllabusAdminTest extends TestCase
{
    use CreeContenu, CreeDonnees, RefreshDatabase;

    private const URL = '/api/admin/syllabus';

    // ---------------------------------------------------------------- Création

    public function test_creation_d_un_syllabus_en_brouillon(): void
    {
        $d = $this->creerDonnees();
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'Nouvel EC']);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson(self::URL, ['ec' => $ec->public_id, 'annee' => $d['annee']->public_id])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.ec.code', 'EC2')
            ->assertJsonPath('data.annee_universitaire.libelle', '2026-2027')
            ->assertJsonPath('data.permissions.modifier', true);

        $this->assertDatabaseHas('syllabus', ['ec_id' => $ec->id, 'statut' => 'brouillon', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'creation', 'entite_type' => 'syllabus']);
    }

    public function test_un_seul_syllabus_par_ec_et_par_annee(): void
    {
        $d = $this->creerDonnees(); // EC1 a déjà un syllabus pour 2026-2027
        $autreAnnee = AnneeUniversitaire::create(['libelle' => '2027-2028']);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson(self::URL, ['ec' => $d['ec']->public_id, 'annee' => $d['annee']->public_id])
            ->assertUnprocessable()->assertJsonValidationErrors('ec');

        // Une autre année est permise
        $this->enTantQue($admin)->postJson(self::URL, ['ec' => $d['ec']->public_id, 'annee' => $autreAnnee->public_id])
            ->assertCreated();
    }

    public function test_la_creation_refuse_les_identifiants_invalides(): void
    {
        $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson(self::URL, [])
            ->assertUnprocessable()->assertJsonValidationErrors(['ec', 'annee']);
        $this->enTantQue($admin)->postJson(self::URL, ['ec' => str_repeat('0', 26), 'annee' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['ec', 'annee']);

        $this->assertSame(1, Syllabus::count());
    }

    public function test_un_enseignant_ne_gere_pas_les_syllabus_de_l_administration(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)->getJson(self::URL)->assertForbidden();
        $this->enTantQue($prof)->postJson(self::URL, ['ec' => $d['ec']->public_id, 'annee' => $d['annee']->public_id])->assertForbidden();
        $this->enTantQue($prof)->deleteJson(self::URL.'/'.$d['syllabus']->public_id)->assertForbidden();

        $this->assertSame(1, Syllabus::count());
    }

    // ---------------------------------------------------------------- Liste

    public function test_liste_et_filtres_des_syllabus(): void
    {
        $d = $this->creerDonnees(); // EC1, publié
        $brouillon = $this->creerSyllabusSupplementaire($d, 'EC2', Statut::Brouillon);
        $prof = $this->enseignant();
        $this->autoriser($brouillon, $prof, true);
        $autreAnnee = AnneeUniversitaire::create(['libelle' => '2027-2028']);
        $admin = $this->administrateur();

        $reponse = $this->enTantQue($admin)->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonMissingPath('data.0.objectifs'); // version allégée, sans le contenu

        $this->enTantQue($admin)->getJson(self::URL.'?statut=brouillon')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ec.code', 'EC2')
            ->assertJsonPath('data.0.enseignants.0.nom', $prof->name)
            ->assertJsonPath('data.0.enseignants.0.est_responsable', true);
        $this->enTantQue($admin)->getJson(self::URL.'?statut=publie')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?q=EC2')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?q=introuvable')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?annee='.$d['annee']->public_id)->assertOk()->assertJsonCount(2, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?annee='.$autreAnnee->public_id)->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?sans_enseignant=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ec.code', 'EC1');
        $this->enTantQue($admin)->getJson(self::URL.'?per_page=1')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?statut=inconnu')->assertUnprocessable();
        $this->enTantQue($admin)->getJson(self::URL.'?annee=abc')->assertUnprocessable();
    }

    // ---------------------------------------------------------------- Suppression

    public function test_suppression_d_un_brouillon_et_de_ses_autorisations(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($this->administrateur())->deleteJson(self::URL.'/'.$d['syllabus']->public_id)->assertNoContent();

        $this->assertDatabaseCount('syllabus', 0);
        $this->assertSame(0, SyllabusAutorisation::count());
        $this->assertSame(1, Ec::count());
        $this->assertDatabaseHas('historique_modifications', ['action' => 'suppression', 'entite_type' => 'syllabus']);
    }

    public function test_un_syllabus_publie_ou_archive_ne_se_supprime_pas(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Publie);
        $archive = $this->creerSyllabusSupplementaire($d, 'EC2', Statut::Archive);
        $admin = $this->administrateur();

        $this->enTantQue($admin)->deleteJson(self::URL.'/'.$d['syllabus']->public_id)
            ->assertStatus(409)
            ->assertJsonPath('message', "Seul un syllabus en brouillon peut être supprimé : un syllabus publié s'archive.");
        $this->enTantQue($admin)->deleteJson(self::URL.'/'.$archive->public_id)->assertStatus(409);

        $this->assertSame(2, Syllabus::count());
    }

    // ---------------------------------------------------------------- Mes syllabus (enseignant)

    public function test_un_enseignant_voit_uniquement_les_syllabus_qui_lui_sont_attribues(): void
    {
        $d = $this->creerDonnees(); // EC1, publié
        $brouillon = $this->creerSyllabusSupplementaire($d, 'EC2', Statut::Brouillon);
        $retire = $this->creerSyllabusSupplementaire($d, 'EC3', Statut::Publie);
        $nonAttribue = $this->creerSyllabusSupplementaire($d, 'EC4', Statut::Publie);
        $prof = $this->enseignant();
        $autre = $this->enseignant();

        $this->autoriser($d['syllabus'], $prof);
        $this->autoriser($brouillon, $prof, true);
        $this->autoriser($retire, $prof)->update(['revoque_le' => now()]);

        $this->enTantQue($prof)->getJson('/api/mes-syllabus')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonMissingPath('data.0.objectifs');

        $this->enTantQue($prof)->getJson('/api/mes-syllabus?statut=brouillon')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ec.code', 'EC2');
        $this->enTantQue($prof)->getJson('/api/mes-syllabus?statut=inconnu')->assertUnprocessable();

        // Un autre enseignant, et un administrateur sans attribution, n'en voient aucun
        $this->enTantQue($autre)->getJson('/api/mes-syllabus')->assertOk()->assertJsonCount(0, 'data');
        $this->enTantQue($this->administrateur())->getJson('/api/mes-syllabus')->assertOk()->assertJsonCount(0, 'data');

        $this->enTantQueVisiteur()->getJson('/api/mes-syllabus')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- Parcours complet

    public function test_de_la_creation_a_la_publication_d_un_syllabus(): void
    {
        $d = $this->creerDonnees();
        $ec = Ec::create(['code' => 'EC2', 'intitule' => 'Nouvel EC']);
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        // 1. L'administrateur crée le syllabus et autorise l'enseignant
        $id = $this->enTantQue($admin)->postJson(self::URL, ['ec' => $ec->public_id, 'annee' => $d['annee']->public_id])
            ->assertCreated()->json('data.id');
        $this->enTantQue($admin)->postJson(self::URL."/{$id}/autorisations", ['user' => $prof->public_id, 'est_responsable' => true])
            ->assertCreated();

        // 2. L'enseignant le retrouve dans sa liste et le complète
        $this->enTantQue($prof)->getJson('/api/mes-syllabus')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
        $this->enTantQue($prof)->putJson("/api/syllabus/{$id}", ['objectifs' => 'Objectifs rédigés par l\'enseignant'])
            ->assertOk()->assertJsonPath('data.objectifs', "Objectifs rédigés par l'enseignant");

        // 3. Tant qu'il est en brouillon, le public ne le voit pas
        $this->enTantQueVisiteur()->getJson("/api/syllabus/{$id}")->assertNotFound();

        // 4. L'administrateur le publie : l'enseignant ne le pouvait pas, le public le voit
        $this->enTantQue($prof)->putJson("/api/syllabus/{$id}", ['statut' => 'publie'])->assertUnprocessable();
        $this->enTantQue($admin)->putJson("/api/syllabus/{$id}", ['statut' => 'publie'])
            ->assertOk()->assertJsonPath('data.statut', 'publie');
        $this->enTantQueVisiteur()->getJson("/api/syllabus/{$id}")
            ->assertOk()->assertJsonPath('data.objectifs', "Objectifs rédigés par l'enseignant");
    }
}
