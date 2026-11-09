<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\HistoriqueModification;
use App\Models\SyllabusAutorisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

/** Règle centrale : un enseignant ne modifie QUE les syllabus pour lesquels il est autorisé. */
class SyllabusPermissionsTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const MESSAGE_403 = "Vous n'avez pas le droit d'effectuer cette action.";

    private function url($syllabus): string
    {
        return "/api/syllabus/{$syllabus->public_id}";
    }

    public function test_un_visiteur_ne_peut_rien_modifier(): void
    {
        $d = $this->creerDonnees();

        $this->putJson($this->url($d['syllabus']), ['objectifs' => 'Piraté'])->assertUnauthorized();

        $this->assertSame('Objectifs initiaux', $d['syllabus']->fresh()->objectifs);
    }

    public function test_un_enseignant_sans_autorisation_est_refuse(): void
    {
        $d = $this->creerDonnees();

        $this->enTantQue($this->enseignant())
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Piraté'])
            ->assertForbidden()
            ->assertJsonPath('message', self::MESSAGE_403);

        $this->assertSame('Objectifs initiaux', $d['syllabus']->fresh()->objectifs);
        $this->assertDatabaseCount('historique_modifications', 0);
    }

    public function test_un_enseignant_autorise_modifie_son_syllabus_et_la_modification_est_journalisee(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Nouveaux objectifs'])
            ->assertOk()
            ->assertJsonPath('data.objectifs', 'Nouveaux objectifs')
            ->assertJsonPath('data.derniere_modification_par', $prof->name);

        $syllabus = $d['syllabus']->fresh();
        $this->assertSame('Nouveaux objectifs', $syllabus->objectifs);
        $this->assertSame($prof->id, $syllabus->updated_by);

        $this->assertDatabaseHas('historique_modifications', [
            'action' => 'modification',
            'entite_type' => 'syllabus',
            'entite_id' => $syllabus->id,
            'user_id' => $prof->id,
        ]);
        $entree = HistoriqueModification::where('action', 'modification')->first();
        $this->assertSame('Objectifs initiaux', $entree->anciennes_valeurs['objectifs']);
        $this->assertSame('Nouveaux objectifs', $entree->nouvelles_valeurs['objectifs']);
    }

    public function test_une_autorisation_sur_un_syllabus_ne_donne_pas_acces_a_un_autre(): void
    {
        $d = $this->creerDonnees();
        $autre = $this->creerSyllabusSupplementaire($d);
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        // Même enseignant, mais en changeant l'identifiant dans l'URL
        $this->enTantQue($prof)
            ->putJson($this->url($autre), ['objectifs' => 'Piraté'])
            ->assertForbidden();

        $this->assertSame('Objectifs de EC2', $autre->fresh()->objectifs);
    }

    public function test_une_autorisation_revoquee_ne_permet_plus_de_modifier(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $autorisation = $this->autoriser($d['syllabus'], $prof);
        $autorisation->update(['revoque_le' => now()]);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Piraté'])
            ->assertForbidden();
    }

    public function test_un_enseignant_desactive_est_bloque_meme_avec_une_autorisation(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant(['is_active' => false]);
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Piraté'])
            ->assertUnauthorized();

        $this->assertSame('Objectifs initiaux', $d['syllabus']->fresh()->objectifs);
    }

    public function test_un_enseignant_ne_peut_pas_modifier_un_syllabus_archive(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Archive);
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Piraté'])
            ->assertForbidden();

        // L'administrateur, lui, le peut
        $this->enTantQue($this->administrateur())
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Corrigé par admin'])
            ->assertOk();
    }

    public function test_un_enseignant_ne_peut_pas_changer_le_statut(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['statut' => 'publie'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');

        $this->assertSame(Statut::Brouillon, $d['syllabus']->fresh()->statut);
    }

    public function test_un_administrateur_peut_publier_un_syllabus(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Brouillon);

        $this->enTantQue($this->administrateur())
            ->putJson($this->url($d['syllabus']), ['statut' => 'publie'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'publie')
            ->assertJsonPath('data.permissions.changer_statut', true);

        $syllabus = $d['syllabus']->fresh();
        $this->assertSame(Statut::Publie, $syllabus->statut);
        $this->assertNotNull($syllabus->published_at);

        $entree = HistoriqueModification::where('action', 'modification')->first();
        $this->assertSame('publie', $entree->nouvelles_valeurs['statut']);
    }

    public function test_un_statut_invalide_est_refuse(): void
    {
        $d = $this->creerDonnees();

        $this->enTantQue($this->administrateur())
            ->putJson($this->url($d['syllabus']), ['statut' => 'nimportequoi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');
    }

    public function test_les_champs_sensibles_envoyes_dans_la_requete_sont_ignores(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);
        $ecId = $d['syllabus']->ec_id;

        $this->enTantQue($prof)->putJson($this->url($d['syllabus']), [
            'objectifs' => 'Nouveaux objectifs',
            'ec_id' => 9999,
            'annee_universitaire_id' => 9999,
            'updated_by' => 9999,
            'created_by' => 9999,
        ])->assertOk();

        $syllabus = $d['syllabus']->fresh();
        $this->assertSame($ecId, $syllabus->ec_id);
        $this->assertSame($d['annee']->id, $syllabus->annee_universitaire_id);
        $this->assertSame($prof->id, $syllabus->updated_by);
        $this->assertNotSame(9999, $syllabus->created_by);
    }

    public function test_une_modification_identique_n_ecrit_rien_et_n_est_pas_journalisee(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => 'Objectifs initiaux'])
            ->assertOk();

        $this->assertDatabaseCount('historique_modifications', 0);
        $this->assertNull($d['syllabus']->fresh()->updated_by);
    }

    public function test_les_sections_supplementaires_sont_validees(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)->putJson($this->url($d['syllabus']), [
            'sections_supplementaires' => [['titre' => 'Annexe', 'contenu' => 'Texte libre']],
        ])->assertOk()->assertJsonPath('data.sections_supplementaires.0.titre', 'Annexe');

        $this->enTantQue($prof)->putJson($this->url($d['syllabus']), [
            'sections_supplementaires' => [['contenu' => 'Sans titre']],
        ])->assertUnprocessable()->assertJsonValidationErrors('sections_supplementaires.0.titre');
    }

    public function test_un_texte_trop_long_est_refuse(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof);

        $this->enTantQue($prof)
            ->putJson($this->url($d['syllabus']), ['objectifs' => str_repeat('a', 15001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('objectifs');
    }

    public function test_un_retrait_conserve_la_trace_de_l_autorisation(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $autorisation = $this->autoriser($d['syllabus'], $prof);
        $autorisation->update(['revoque_le' => now()]);

        $this->assertSame(1, SyllabusAutorisation::count());
        $this->assertFalse($d['syllabus']->estAutorisePour($prof));
    }
}
