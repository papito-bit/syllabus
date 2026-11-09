<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class ValidationFrancaisTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fr');
    }

    public function test_les_messages_de_validation_sont_en_francais(): void
    {
        $reponse = $this->depuisLeFrontend()->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Le champ adresse e-mail est obligatoire.')
            ->assertJsonPath('errors.password.0', 'Le champ mot de passe est obligatoire.')
            ->assertJsonPath('message', 'Le champ adresse e-mail est obligatoire. (et 1 autre erreur)');

        $this->assertStringNotContainsString('validation.', json_encode($reponse->json('errors')));
    }

    public function test_un_email_invalide_est_signale_en_francais(): void
    {
        $this->depuisLeFrontend()->postJson('/api/login', ['email' => 'pas-un-email', 'password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Le champ adresse e-mail doit être une adresse e-mail valide.');
    }

    public function test_les_champs_d_administration_utilisent_des_noms_lisibles(): void
    {
        $this->enTantQue($this->administrateur())->postJson('/api/admin/niveaux', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Le champ code est obligatoire.')
            ->assertJsonPath('errors.libelle.0', 'Le champ libellé est obligatoire.');
    }

    public function test_un_doublon_est_signale_en_francais(): void
    {
        $this->creerDonnees();

        $reponse = $this->enTantQue($this->administrateur())
            ->postJson('/api/admin/niveaux', ['code' => 'L2', 'libelle' => 'Doublon'])
            ->assertUnprocessable();

        $this->assertStringContainsString('déjà utilisée', implode(' ', $reponse->json('errors.code')));
    }

    public function test_les_regles_du_mot_de_passe_sont_en_francais(): void
    {
        $prof = $this->enseignant();

        $reponse = $this->enTantQue($prof)->putJson('/api/profil/mot-de-passe', [
            'current_password' => 'mot-de-passe-de-test',
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertUnprocessable();

        $messages = implode(' ', $reponse->json('errors.password'));
        $this->assertStringContainsString('au moins 10 caractères', $messages);
        $this->assertStringNotContainsString('validation.', $messages);
    }

    public function test_un_mauvais_mot_de_passe_actuel_est_signale_en_francais(): void
    {
        $reponse = $this->enTantQue($this->enseignant())->putJson('/api/profil/mot-de-passe', [
            'current_password' => 'pas-le-bon',
            'password' => 'NouveauMotDePasse42',
            'password_confirmation' => 'NouveauMotDePasse42',
        ])->assertUnprocessable();

        $this->assertSame('Le mot de passe est incorrect.', $reponse->json('errors.current_password.0'));
    }
}
