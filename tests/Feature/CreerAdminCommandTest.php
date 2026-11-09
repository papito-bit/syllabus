<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class CreerAdminCommandTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    private const QUESTION_MOT_DE_PASSE = 'Mot de passe (10 caractères minimum, avec majuscule, minuscule et chiffre)';

    private const QUESTION_CONFIRMATION = 'Confirmez le mot de passe';

    public function test_cree_un_administrateur_avec_un_mot_de_passe_hache(): void
    {
        $this->artisan('admin:creer', ['--nom' => 'Admin Test', '--email' => 'Admin@Exemple.Test'])
            ->expectsQuestion(self::QUESTION_MOT_DE_PASSE, 'MotDePasse2026')
            ->expectsQuestion(self::QUESTION_CONFIRMATION, 'MotDePasse2026')
            ->expectsOutputToContain('Administrateur créé')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@exemple.test')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->estAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertNotSame('MotDePasse2026', $admin->password);
        $this->assertTrue(Hash::check('MotDePasse2026', $admin->password));
        $this->assertSame(26, strlen($admin->public_id));
        $this->assertDatabaseHas('historique_modifications', ['action' => 'creation_admin']);
    }

    public function test_refuse_un_mot_de_passe_trop_faible(): void
    {
        $this->artisan('admin:creer', ['--nom' => 'Admin Test', '--email' => 'admin@exemple.test'])
            ->expectsQuestion(self::QUESTION_MOT_DE_PASSE, 'faible')
            ->expectsQuestion(self::QUESTION_CONFIRMATION, 'faible')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_refuse_une_confirmation_differente(): void
    {
        $this->artisan('admin:creer', ['--nom' => 'Admin Test', '--email' => 'admin@exemple.test'])
            ->expectsQuestion(self::QUESTION_MOT_DE_PASSE, 'MotDePasse2026')
            ->expectsQuestion(self::QUESTION_CONFIRMATION, 'AutreMotDePasse2026')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_refuse_un_email_deja_utilise(): void
    {
        $this->enseignant(['email' => 'admin@exemple.test']);

        $this->artisan('admin:creer', ['--nom' => 'Admin Test', '--email' => 'admin@exemple.test'])
            ->expectsQuestion(self::QUESTION_MOT_DE_PASSE, 'MotDePasse2026')
            ->expectsQuestion(self::QUESTION_CONFIRMATION, 'MotDePasse2026')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
