<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\AnneeUniversitaire;
use App\Models\Maquette;
use App\Models\Niveau;
use App\Models\Parcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class ConsultationPubliqueTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private function urlMaquette(array $d): string
    {
        return "/api/niveaux/{$d['niveau']->public_id}/parcours/{$d['parcours']->public_id}/maquette";
    }

    public function test_les_niveaux_sont_publics_et_n_exposent_que_des_identifiants_publics(): void
    {
        $this->creerDonnees();

        $reponse = $this->getJson('/api/niveaux');

        $reponse->assertOk()
            ->assertJsonPath('data.0.code', 'L2')
            ->assertJsonPath('data.0.parcours_count', 1);
        $this->assertSame(26, strlen($reponse->json('data.0.id')));
    }

    public function test_les_parcours_d_un_niveau_sont_listes(): void
    {
        $d = $this->creerDonnees();

        $this->getJson("/api/niveaux/{$d['niveau']->public_id}/parcours")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'P-TEST');
    }

    public function test_un_parcours_inactif_n_est_pas_propose(): void
    {
        $d = $this->creerDonnees();
        $d['parcours']->update(['is_active' => false]);

        $this->getJson("/api/niveaux/{$d['niveau']->public_id}/parcours")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_la_maquette_d_un_parcours_est_affichee_avec_ses_ue_et_ses_ec(): void
    {
        $d = $this->creerDonnees();

        $this->getJson($this->urlMaquette($d))
            ->assertOk()
            ->assertJsonPath('data.id', $d['maquette']->public_id)
            ->assertJsonPath('data.statut', 'publie')
            ->assertJsonPath('data.niveau.code', 'L2')
            ->assertJsonPath('data.semestres.0.semestre.code', 'S3')
            ->assertJsonPath('data.semestres.0.unites.0.code', 'UE1')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.ec.code', 'EC1')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.heures_total', '40.0')
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.syllabus_id', $d['syllabus']->public_id);
    }

    public function test_une_maquette_en_brouillon_est_invisible_pour_le_public(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->getJson($this->urlMaquette($d))->assertNotFound();
        $this->getJson("/api/maquettes/{$d['maquette']->public_id}")->assertNotFound();

        // Un enseignant n'y a pas accès non plus
        $this->enTantQue($this->enseignant())
            ->getJson("/api/maquettes/{$d['maquette']->public_id}")->assertNotFound();
    }

    public function test_un_administrateur_voit_une_maquette_en_brouillon(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon);

        $this->enTantQue($this->administrateur())
            ->getJson("/api/maquettes/{$d['maquette']->public_id}")
            ->assertOk()
            ->assertJsonPath('data.statut', 'brouillon');
    }

    public function test_un_parcours_non_associe_au_niveau_donne_404(): void
    {
        $d = $this->creerDonnees();
        $autre = Parcours::create(['code' => 'P-AUTRE', 'nom' => 'Autre parcours']);

        $this->getJson("/api/niveaux/{$d['niveau']->public_id}/parcours/{$autre->public_id}/maquette")
            ->assertNotFound();
    }

    public function test_la_maquette_de_l_annee_courante_est_preferee(): void
    {
        $d = $this->creerDonnees();
        $ancienne = AnneeUniversitaire::create(['libelle' => '2025-2026', 'is_courante' => false]);
        Maquette::create([
            'niveau_id' => $d['niveau']->id,
            'parcours_id' => $d['parcours']->id,
            'annee_universitaire_id' => $ancienne->id,
            'version' => 1,
            'statut' => Statut::Publie,
            'published_at' => now(),
        ]);

        $this->getJson($this->urlMaquette($d))
            ->assertOk()
            ->assertJsonPath('data.id', $d['maquette']->public_id);
    }

    public function test_le_lien_vers_un_syllabus_en_brouillon_est_cache_au_public(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);

        $this->getJson($this->urlMaquette($d))
            ->assertOk()
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.syllabus_id', null);

        $this->enTantQue($this->administrateur())->getJson($this->urlMaquette($d))
            ->assertJsonPath('data.semestres.0.unites.0.lignes.0.syllabus_id', $d['syllabus']->public_id);
    }

    public function test_la_liste_des_maquettes_ne_montre_que_les_publiees_au_public(): void
    {
        $d = $this->creerDonnees();
        $this->creerMaquetteSupplementaire($d, Statut::Brouillon);

        $this->getJson('/api/maquettes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonMissingPath('data.0.semestres');

        // Un visiteur ne peut pas forcer l'affichage des brouillons avec ?statut=
        $this->getJson('/api/maquettes?statut=brouillon')->assertOk()->assertJsonCount(1, 'data');

        $admin = $this->administrateur();
        $this->enTantQue($admin)->getJson('/api/maquettes')->assertJsonCount(2, 'data');
        $this->enTantQue($admin)->getJson('/api/maquettes?statut=brouillon')->assertJsonCount(1, 'data');
    }

    public function test_les_filtres_de_la_liste_fonctionnent_et_sont_valides(): void
    {
        $d = $this->creerDonnees();
        $autreNiveau = Niveau::create(['code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3]);

        $this->getJson("/api/maquettes?niveau={$d['niveau']->public_id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/maquettes?niveau={$autreNiveau->public_id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/maquettes?niveau=abc')->assertUnprocessable();
    }

    public function test_un_syllabus_publie_est_public_avec_son_contexte(): void
    {
        $d = $this->creerDonnees();
        $enseignant = $this->enseignant();
        $this->autoriser($d['syllabus'], $enseignant, true);

        $reponse = $this->getJson("/api/syllabus/{$d['syllabus']->public_id}");

        $reponse->assertOk()
            ->assertJsonPath('data.ec.code', 'EC1')
            ->assertJsonPath('data.objectifs', 'Objectifs initiaux')
            ->assertJsonPath('data.permissions.modifier', false)
            ->assertJsonPath('data.permissions.changer_statut', false)
            ->assertJsonPath('data.enseignants.0.nom', $enseignant->name)
            ->assertJsonPath('data.enseignants.0.est_responsable', true)
            ->assertJsonPath('contexte.0.semestre', 'S3')
            ->assertJsonPath('contexte.0.niveau.code', 'L2')
            ->assertJsonPath('contexte.0.heures_total', '40.0');

        // L'e-mail d'un enseignant n'est jamais exposé au public
        $this->assertStringNotContainsString($enseignant->email, $reponse->getContent());
    }

    public function test_le_contexte_d_un_syllabus_ignore_les_maquettes_non_publiees(): void
    {
        $d = $this->creerDonnees(Statut::Brouillon, Statut::Publie);

        $this->getJson("/api/syllabus/{$d['syllabus']->public_id}")
            ->assertOk()
            ->assertJsonCount(0, 'contexte');
    }

    public function test_un_syllabus_en_brouillon_est_invisible_sauf_pour_les_personnes_autorisees(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);
        $url = "/api/syllabus/{$d['syllabus']->public_id}";
        $autorise = $this->enseignant();
        $this->autoriser($d['syllabus'], $autorise);

        $this->getJson($url)->assertNotFound();
        $this->enTantQue($this->enseignant())->getJson($url)->assertNotFound();
        $this->enTantQue($autorise)->getJson($url)->assertOk()->assertJsonPath('data.permissions.modifier', true);
        $this->enTantQue($this->administrateur())->getJson($url)->assertOk();
    }

    public function test_un_syllabus_archive_est_invisible_pour_le_public(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Archive);

        $this->getJson("/api/syllabus/{$d['syllabus']->public_id}")->assertNotFound();
    }

    public function test_les_erreurs_de_l_api_sont_en_json_francais(): void
    {
        $this->getJson('/api/syllabus/identifiant-inexistant')
            ->assertNotFound()
            ->assertJsonPath('message', 'Ressource introuvable.');

        $this->getJson('/api/route-inexistante')
            ->assertNotFound()
            ->assertJsonPath('message', 'Ressource introuvable.');

        $this->postJson('/api/niveaux')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Méthode non autorisée.');
    }
}
