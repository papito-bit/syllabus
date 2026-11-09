<?php

namespace Tests\Feature;

use App\Models\SyllabusAutorisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

/** Gestion des droits d'édition par un administrateur. */
class AutorisationsAdminTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private function url($syllabus, string $suffixe = ''): string
    {
        return "/api/admin/syllabus/{$syllabus->public_id}/autorisations{$suffixe}";
    }

    public function test_un_visiteur_est_refuse(): void
    {
        $d = $this->creerDonnees();

        $this->getJson($this->url($d['syllabus']))->assertUnauthorized();
        $this->postJson($this->url($d['syllabus']), [])->assertUnauthorized();
    }

    public function test_un_enseignant_ne_peut_pas_gerer_les_autorisations(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $complice = $this->enseignant();

        $this->enTantQue($prof)->getJson($this->url($d['syllabus']))->assertForbidden();

        // Il ne peut pas s'accorder un droit, ni en donner à quelqu'un d'autre
        $this->enTantQue($prof)->postJson($this->url($d['syllabus']), ['user' => $prof->public_id])->assertForbidden();
        $this->enTantQue($prof)->postJson($this->url($d['syllabus']), ['user' => $complice->public_id])->assertForbidden();

        $this->assertDatabaseCount('syllabus_autorisations', 0);
    }

    public function test_un_administrateur_accorde_un_droit_et_l_enseignant_peut_alors_modifier(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        $this->enTantQue($admin)
            ->postJson($this->url($d['syllabus']), ['user' => $prof->public_id, 'est_responsable' => true])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $prof->public_id)
            ->assertJsonPath('data.est_responsable', true)
            ->assertJsonPath('data.accorde_par', $admin->name);

        $this->assertDatabaseHas('syllabus_autorisations', [
            'syllabus_id' => $d['syllabus']->id,
            'user_id' => $prof->id,
            'revoque_le' => null,
        ]);
        $this->assertDatabaseHas('historique_modifications', [
            'action' => 'octroi_autorisation',
            'user_id' => $admin->id,
            'entite_id' => $d['syllabus']->id,
        ]);

        $this->enTantQue($prof)
            ->putJson("/api/syllabus/{$d['syllabus']->public_id}", ['objectifs' => 'Modifié par le prof'])
            ->assertOk();
    }

    public function test_accorder_deux_fois_met_a_jour_sans_creer_de_doublon(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $prof->public_id])->assertCreated();
        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $prof->public_id, 'est_responsable' => true])->assertOk();

        $this->assertSame(1, SyllabusAutorisation::count());
        $this->assertTrue((bool) SyllabusAutorisation::first()->est_responsable);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'modification_autorisation']);
    }

    public function test_un_seul_enseignant_responsable_par_syllabus(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $premier = $this->enseignant();
        $second = $this->enseignant();

        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $premier->public_id, 'est_responsable' => true])->assertCreated();
        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $second->public_id, 'est_responsable' => true])->assertCreated();

        $this->assertFalse((bool) SyllabusAutorisation::where('user_id', $premier->id)->first()->est_responsable);
        $this->assertTrue((bool) SyllabusAutorisation::where('user_id', $second->id)->first()->est_responsable);
    }

    public function test_seul_un_enseignant_actif_peut_recevoir_un_droit(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $autreAdmin = $this->administrateur();
        $inactif = $this->enseignant(['is_active' => false]);

        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $autreAdmin->public_id])
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => $inactif->public_id])
            ->assertUnprocessable()->assertJsonValidationErrors('user');

        $this->assertDatabaseCount('syllabus_autorisations', 0);
    }

    public function test_un_identifiant_inconnu_ou_mal_forme_est_refuse(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => str_repeat('0', 26)])
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), ['user' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->enTantQue($admin)->postJson($this->url($d['syllabus']), [])
            ->assertUnprocessable()->assertJsonValidationErrors('user');
    }

    public function test_la_liste_ne_montre_que_les_autorisations_actives(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $actif = $this->enseignant();
        $retire = $this->enseignant();
        $this->autoriser($d['syllabus'], $actif);
        $this->autoriser($d['syllabus'], $retire)->update(['revoque_le' => now()]);

        $this->enTantQue($admin)->getJson($this->url($d['syllabus']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $actif->public_id);
    }

    public function test_le_retrait_revoque_le_droit_sans_effacer_la_trace(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof, true);

        $this->enTantQue($admin)
            ->deleteJson($this->url($d['syllabus'], "/{$prof->public_id}"))
            ->assertNoContent();

        $autorisation = SyllabusAutorisation::first();
        $this->assertNotNull($autorisation);               // la ligne est conservée
        $this->assertNotNull($autorisation->revoque_le);   // mais révoquée
        $this->assertFalse((bool) $autorisation->est_responsable);
        $this->assertDatabaseHas('historique_modifications', ['action' => 'retrait_autorisation']);

        // L'enseignant ne peut plus modifier
        $this->enTantQue($prof)
            ->putJson("/api/syllabus/{$d['syllabus']->public_id}", ['objectifs' => 'Piraté'])
            ->assertForbidden();

        // Retirer à nouveau un droit déjà retiré : introuvable
        $this->enTantQue($admin)
            ->deleteJson($this->url($d['syllabus'], "/{$prof->public_id}"))
            ->assertNotFound();
    }

    public function test_un_droit_retire_peut_etre_accorde_a_nouveau(): void
    {
        $d = $this->creerDonnees();
        $admin = $this->administrateur();
        $prof = $this->enseignant();
        $this->autoriser($d['syllabus'], $prof)->update(['revoque_le' => now()]);

        $this->enTantQue($admin)
            ->postJson($this->url($d['syllabus']), ['user' => $prof->public_id])
            ->assertCreated();

        $this->assertNull(SyllabusAutorisation::first()->revoque_le);
        $this->assertSame(1, SyllabusAutorisation::count());
    }

    public function test_un_enseignant_ne_peut_pas_retirer_un_droit(): void
    {
        $d = $this->creerDonnees();
        $prof = $this->enseignant();
        $autre = $this->enseignant();
        $this->autoriser($d['syllabus'], $autre);

        $this->enTantQue($prof)
            ->deleteJson($this->url($d['syllabus'], "/{$autre->public_id}"))
            ->assertForbidden();

        $this->assertNull(SyllabusAutorisation::first()->revoque_le);
    }
}
