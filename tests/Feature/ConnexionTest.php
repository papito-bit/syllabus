<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const MOT_DE_PASSE = 'mot-de-passe-de-test'; // défini dans UserFactory

    public function test_un_enseignant_actif_peut_se_connecter(): void
    {
        $user = $this->enseignant(['email' => 'prof@exemple.test']);

        $reponse = $this->depuisLeFrontend()->postJson('/api/login', [
            'email' => 'prof@exemple.test',
            'password' => self::MOT_DE_PASSE,
        ]);

        $reponse->assertOk()
            ->assertJsonPath('data.email', 'prof@exemple.test')
            ->assertJsonPath('data.role', 'enseignant')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('historique_modifications', ['user_id' => $user->id, 'action' => 'connexion']);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $this->enseignant(['email' => 'prof@exemple.test']);

        $this->depuisLeFrontend()->postJson('/api/login', [
            'email' => 'prof@exemple.test',
            'password' => 'mauvais-mot-de-passe',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_un_email_inconnu_donne_la_meme_erreur(): void
    {
        $this->depuisLeFrontend()->postJson('/api/login', [
            'email' => 'inconnu@exemple.test',
            'password' => self::MOT_DE_PASSE,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactif()->create(['email' => 'inactif@exemple.test']);

        $this->depuisLeFrontend()->postJson('/api/login', [
            'email' => 'inactif@exemple.test',
            'password' => self::MOT_DE_PASSE,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_la_connexion_exige_email_et_mot_de_passe(): void
    {
        $this->depuisLeFrontend()->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_la_connexion_est_refusee_hors_du_frontend(): void
    {
        $this->enseignant(['email' => 'prof@exemple.test']);

        // Sans en-tête Referer/Origin du frontend déclaré, il n'y a pas de session
        $this->postJson('/api/login', [
            'email' => 'prof@exemple.test',
            'password' => self::MOT_DE_PASSE,
        ])->assertStatus(419)
            ->assertJsonPath('message', "Connexion possible uniquement depuis l'application web.");

        $this->assertGuest('web');
    }

    public function test_la_connexion_est_limitee_a_cinq_essais_par_minute(): void
    {
        $this->enseignant(['email' => 'prof@exemple.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->depuisLeFrontend()->postJson('/api/login', [
                'email' => 'prof@exemple.test',
                'password' => 'mauvais-mot-de-passe',
            ])->assertUnprocessable();
        }

        $this->depuisLeFrontend()->postJson('/api/login', [
            'email' => 'prof@exemple.test',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Trop de requêtes. Patientez quelques instants.');
    }

    public function test_un_visiteur_non_connecte_recoit_401_en_francais(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Authentification requise.');
    }

    public function test_un_utilisateur_connecte_voit_son_profil_sans_donnee_sensible(): void
    {
        $user = $this->enseignant();

        $reponse = $this->actingAs($user)->getJson('/api/user');

        $reponse->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertSame(26, strlen($reponse->json('data.id')));
        $reponse->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
    }

    public function test_la_deconnexion_termine_la_session(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->depuisLeFrontend()->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_un_compte_desactive_en_cours_de_session_est_coupe(): void
    {
        $user = User::factory()->inactif()->create();

        $this->actingAs($user)->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Ce compte est désactivé.');
    }
}
