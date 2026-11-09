<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\HistoriqueModification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const MOT_DE_PASSE = 'mot-de-passe-de-test';

    public function test_un_visiteur_ne_peut_pas_modifier_un_profil(): void
    {
        $this->putJson('/api/profil', ['name' => 'X', 'email' => 'x@exemple.test'])->assertUnauthorized();
        $this->putJson('/api/profil/mot-de-passe', [])->assertUnauthorized();
    }

    public function test_un_utilisateur_modifie_son_nom_et_son_email(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil', [
            'name' => 'Nouveau Nom',
            'email' => 'nouveau@exemple.test',
        ])->assertOk()->assertJsonPath('data.name', 'Nouveau Nom');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nouveau Nom', 'email' => 'nouveau@exemple.test']);
    }

    public function test_un_email_deja_utilise_est_refuse(): void
    {
        $this->enseignant(['email' => 'pris@exemple.test']);
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil', [
            'name' => 'Nom',
            'email' => 'pris@exemple.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_un_utilisateur_ne_peut_pas_se_promouvoir_administrateur(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil', [
            'name' => 'Nom',
            'email' => $user->email,
            'role' => 'admin',
            'is_active' => false,
        ])->assertOk();

        $this->assertSame(Role::Enseignant, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_changement_de_mot_de_passe_reussi(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil/mot-de-passe', [
            'current_password' => self::MOT_DE_PASSE,
            'password' => 'NouveauMotDePasse42',
            'password_confirmation' => 'NouveauMotDePasse42',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauMotDePasse42', $user->fresh()->password));

        // Le journal ne contient jamais de mot de passe
        $entree = HistoriqueModification::where('action', 'changement_mot_de_passe')->first();
        $this->assertNotNull($entree);
        $this->assertNull($entree->anciennes_valeurs);
        $this->assertNull($entree->nouvelles_valeurs);
    }

    public function test_le_mot_de_passe_actuel_doit_etre_correct(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil/mot-de-passe', [
            'current_password' => 'pas-le-bon',
            'password' => 'NouveauMotDePasse42',
            'password_confirmation' => 'NouveauMotDePasse42',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::MOT_DE_PASSE, $user->fresh()->password));
    }

    public function test_un_mot_de_passe_trop_faible_est_refuse(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil/mot-de-passe', [
            'current_password' => self::MOT_DE_PASSE,
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_la_confirmation_doit_correspondre(): void
    {
        $user = $this->enseignant();

        $this->actingAs($user)->putJson('/api/profil/mot-de-passe', [
            'current_password' => self::MOT_DE_PASSE,
            'password' => 'NouveauMotDePasse42',
            'password_confirmation' => 'AutreMotDePasse42',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
