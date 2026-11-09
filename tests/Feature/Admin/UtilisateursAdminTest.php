<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\HistoriqueModification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class UtilisateursAdminTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const URL = '/api/admin/utilisateurs';

    private function ouvrirUneSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    // ---------------------------------------------------------------- Création

    public function test_un_enseignant_ne_peut_pas_gerer_les_comptes(): void
    {
        $prof = $this->enseignant();
        $cible = $this->enseignant();

        $this->enTantQue($prof)->postJson(self::URL, [
            'name' => 'Pirate', 'email' => 'pirate@exemple.test', 'role' => 'admin', 'password' => 'MotDePasse2026',
        ])->assertForbidden();
        $this->enTantQue($prof)->putJson(self::URL.'/'.$cible->public_id, [
            'name' => 'X', 'email' => $cible->email, 'role' => 'admin',
        ])->assertForbidden();
        $this->enTantQue($prof)->postJson(self::URL.'/'.$cible->public_id.'/mot-de-passe')->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'pirate@exemple.test']);
        $this->assertSame(Role::Enseignant, $cible->fresh()->role);
    }

    public function test_creation_d_un_compte_avec_un_mot_de_passe_choisi(): void
    {
        $admin = $this->administrateur();

        $reponse = $this->enTantQue($admin)->postJson(self::URL, [
            'name' => 'Prof Nouveau',
            'email' => ' Nouveau@Exemple.Test ',
            'role' => 'enseignant',
            'password' => 'MotDePasse2026',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'nouveau@exemple.test')
            ->assertJsonPath('data.role', 'enseignant')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('mot_de_passe_temporaire')
            ->assertJsonMissingPath('data.password');

        $this->assertSame(26, strlen($reponse->json('data.id')));

        $user = User::where('email', 'nouveau@exemple.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('MotDePasse2026', $user->password));
        $this->assertNotSame('MotDePasse2026', $user->password);

        // Le journal ne contient aucun mot de passe
        $entree = HistoriqueModification::where('action', 'creation_utilisateur')->first();
        $this->assertNotNull($entree);
        $this->assertArrayNotHasKey('password', $entree->nouvelles_valeurs);
        $this->assertSame('enseignant', $entree->nouvelles_valeurs['role']);
    }

    public function test_sans_mot_de_passe_un_mot_de_passe_temporaire_est_genere_et_affiche_une_fois(): void
    {
        $admin = $this->administrateur(['name' => 'Chef Admin', 'email' => 'chef@exemple.test']);

        $reponse = $this->enTantQue($admin)->postJson(self::URL, [
            'name' => 'Prof Temporaire', 'email' => 'temp@exemple.test', 'role' => 'enseignant',
        ])->assertCreated();

        $motDePasse = $reponse->json('mot_de_passe_temporaire');
        $this->assertSame(16, strlen($motDePasse));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));

        $user = User::where('email', 'temp@exemple.test')->first();
        $this->assertTrue(Hash::check($motDePasse, $user->password));

        // Il n'est retrouvable nulle part ensuite
        $this->enTantQue($admin)->getJson(self::URL.'?q=temp')->assertOk()
            ->assertJsonMissingPath('data.0.mot_de_passe_temporaire');
        $this->assertStringNotContainsString($motDePasse, serialize(HistoriqueModification::all()->toArray()));
    }

    public function test_un_administrateur_peut_creer_un_autre_administrateur(): void
    {
        $this->enTantQue($this->administrateur())->postJson(self::URL, [
            'name' => 'Second Admin', 'email' => 'admin2@exemple.test', 'role' => 'admin', 'password' => 'MotDePasse2026',
        ])->assertCreated()->assertJsonPath('data.role', 'admin');

        $this->assertSame(Role::Admin, User::where('email', 'admin2@exemple.test')->first()->role);
    }

    public function test_la_creation_refuse_les_donnees_invalides(): void
    {
        $admin = $this->administrateur();
        $existant = $this->enseignant(['email' => 'pris@exemple.test']);

        $this->enTantQue($admin)->postJson(self::URL, [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'role']);

        $this->enTantQue($admin)->postJson(self::URL, [
            'name' => 'X', 'email' => 'pris@exemple.test', 'role' => 'enseignant',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->enTantQue($admin)->postJson(self::URL, [
            'name' => 'X', 'email' => 'x@exemple.test', 'role' => 'superadmin',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->enTantQue($admin)->postJson(self::URL, [
            'name' => 'X', 'email' => 'x@exemple.test', 'role' => 'enseignant', 'password' => 'faible',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'x@exemple.test']);
    }

    // ---------------------------------------------------------------- Liste

    public function test_liste_paginee_avec_recherche_et_filtres(): void
    {
        $admin = $this->administrateur(['name' => 'Chef Admin', 'email' => 'chef@exemple.test']);
        $this->enseignant(['name' => 'Alice Martin', 'email' => 'alice@exemple.test']);
        $this->enseignant(['name' => 'Bruno Durand', 'email' => 'bruno@exemple.test']);
        $this->enseignant(['name' => 'Inactif Test', 'email' => 'inactif@exemple.test', 'is_active' => false]);

        $this->enTantQue($admin)->getJson(self::URL)
            ->assertOk()->assertJsonCount(4, 'data')->assertJsonStructure(['data', 'links', 'meta']);

        $this->enTantQue($admin)->getJson(self::URL.'?q=alice')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'alice@exemple.test');
        $this->enTantQue($admin)->getJson(self::URL.'?q=durand')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?role=admin')->assertOk()->assertJsonCount(1, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?role=enseignant')->assertOk()->assertJsonCount(3, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?actif=0')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'inactif@exemple.test');
        $this->enTantQue($admin)->getJson(self::URL.'?actif=1')->assertOk()->assertJsonCount(3, 'data');
        $this->enTantQue($admin)->getJson(self::URL.'?role=invalide')->assertUnprocessable();
        $this->enTantQue($admin)->getJson(self::URL.'?per_page=1')->assertOk()->assertJsonCount(1, 'data');
    }

    // ---------------------------------------------------------------- Modification

    public function test_promotion_d_un_enseignant_journalisee(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => $prof->name, 'email' => $prof->email, 'role' => 'admin',
        ])->assertOk()->assertJsonPath('data.role', 'admin');

        $this->assertSame(Role::Admin, $prof->fresh()->role);

        $entree = HistoriqueModification::where('action', 'modification_utilisateur')->first();
        $this->assertNotNull($entree);
        $this->assertSame('admin', $entree->nouvelles_valeurs['role']);
        $this->assertSame('enseignant', $entree->anciennes_valeurs['role']);
    }

    public function test_une_modification_identique_n_est_pas_journalisee(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => $prof->name, 'email' => $prof->email, 'role' => 'enseignant',
        ])->assertOk();

        $this->assertDatabaseCount('historique_modifications', 0);
    }

    public function test_le_mot_de_passe_ne_se_modifie_pas_par_la_mise_a_jour(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant();

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => $prof->name, 'email' => $prof->email, 'role' => 'enseignant', 'password' => 'MotDePasse2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('mot-de-passe-de-test', $prof->fresh()->password));
    }

    public function test_la_mise_a_jour_refuse_un_email_deja_pris_ou_un_role_invalide(): void
    {
        $admin = $this->administrateur();
        $autre = $this->enseignant(['email' => 'pris@exemple.test']);
        $prof = $this->enseignant();

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => 'X', 'email' => 'pris@exemple.test', 'role' => 'enseignant',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => 'X', 'email' => $prof->email, 'role' => 'nimportequoi',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_un_administrateur_ne_peut_pas_modifier_son_propre_role_ni_se_desactiver(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->putJson(self::URL.'/'.$admin->public_id, [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'enseignant',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->enTantQue($admin)->putJson(self::URL.'/'.$admin->public_id, [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $fresh = $admin->fresh();
        $this->assertSame(Role::Admin, $fresh->role);
        $this->assertTrue($fresh->is_active);

        // Modifier son propre nom reste possible
        $this->enTantQue($admin)->putJson(self::URL.'/'.$admin->public_id, [
            'name' => 'Nouveau Nom Admin', 'email' => $admin->email, 'role' => 'admin',
        ])->assertOk()->assertJsonPath('data.name', 'Nouveau Nom Admin');
    }

    public function test_desactiver_un_compte_coupe_ses_sessions_et_bloque_ses_requetes(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant();
        $this->ouvrirUneSession($prof);

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => $prof->name, 'email' => $prof->email, 'role' => 'enseignant', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertDatabaseMissing('sessions', ['user_id' => $prof->id]);

        $this->enTantQue($prof->fresh())->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Ce compte est désactivé.');
    }

    public function test_un_compte_desactive_peut_etre_reactive(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant(['is_active' => false]);

        $this->enTantQue($admin)->putJson(self::URL.'/'.$prof->public_id, [
            'name' => $prof->name, 'email' => $prof->email, 'role' => 'enseignant', 'is_active' => true,
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertTrue($prof->fresh()->is_active);
    }

    // ---------------------------------------------------------------- Réinitialisation

    public function test_reinitialisation_du_mot_de_passe(): void
    {
        $admin = $this->administrateur();
        $prof = $this->enseignant();
        $this->ouvrirUneSession($prof);

        $reponse = $this->enTantQue($admin)->postJson(self::URL.'/'.$prof->public_id.'/mot-de-passe')->assertOk();

        $motDePasse = $reponse->json('mot_de_passe_temporaire');
        $this->assertSame(16, strlen($motDePasse));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));

        $fresh = $prof->fresh();
        $this->assertTrue(Hash::check($motDePasse, $fresh->password));
        $this->assertFalse(Hash::check('mot-de-passe-de-test', $fresh->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $prof->id]);

        $entree = HistoriqueModification::where('action', 'reinitialisation_mot_de_passe')->first();
        $this->assertNotNull($entree);
        $this->assertNull($entree->anciennes_valeurs);
        $this->assertNull($entree->nouvelles_valeurs);
        $this->assertStringNotContainsString($motDePasse, serialize(HistoriqueModification::all()->toArray()));
    }

    public function test_un_administrateur_ne_reinitialise_pas_son_propre_mot_de_passe_ici(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->postJson(self::URL.'/'.$admin->public_id.'/mot-de-passe')
            ->assertUnprocessable()->assertJsonValidationErrors('user');

        $this->assertTrue(Hash::check('mot-de-passe-de-test', $admin->fresh()->password));
    }

    public function test_un_compte_inconnu_donne_404(): void
    {
        $admin = $this->administrateur();

        $this->enTantQue($admin)->putJson(self::URL.'/'.str_repeat('0', 26), [
            'name' => 'X', 'email' => 'x@exemple.test', 'role' => 'enseignant',
        ])->assertNotFound();
        $this->enTantQue($admin)->postJson(self::URL.'/'.str_repeat('0', 26).'/mot-de-passe')->assertNotFound();
    }
}
