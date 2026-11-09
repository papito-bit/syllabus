<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Maquette;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreeDonnees;
use Tests\TestCase;

class PoliciesEtIdentifiantsTest extends TestCase
{
    use CreeDonnees, RefreshDatabase;

    public function test_politique_de_consultation_des_maquettes(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $brouillon = $this->creerMaquetteSupplementaire($d, Statut::Brouillon, 2);
        $archive = $this->creerMaquetteSupplementaire($d, Statut::Archive, 3);
        $prof = $this->enseignant();
        $admin = $this->administrateur();

        // Visiteur (null) : seulement le publié
        $this->assertTrue(Gate::forUser(null)->allows('view', $d['maquette']));
        $this->assertFalse(Gate::forUser(null)->allows('view', $brouillon));
        $this->assertFalse(Gate::forUser(null)->allows('view', $archive));

        // Enseignant : pas plus de droits qu'un visiteur sur les maquettes
        $this->assertTrue(Gate::forUser($prof)->allows('view', $d['maquette']));
        $this->assertFalse(Gate::forUser($prof)->allows('view', $brouillon));

        // Administrateur : tout
        $this->assertTrue(Gate::forUser($admin)->allows('view', $brouillon));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $archive));
    }

    public function test_politique_de_modification_des_maquettes(): void
    {
        $d = $this->creerDonnees(Statut::Publie);
        $brouillon = $this->creerMaquetteSupplementaire($d, Statut::Brouillon, 2);
        $prof = $this->enseignant();
        $admin = $this->administrateur();

        // Un enseignant ne peut rien écrire
        foreach (['update', 'publish', 'archive', 'delete'] as $action) {
            $this->assertFalse(Gate::forUser($prof)->allows($action, $brouillon), "enseignant : {$action}");
        }
        $this->assertFalse($prof->can('create', Maquette::class));
        $this->assertTrue($admin->can('create', Maquette::class));

        // Administrateur : modifier/supprimer/publier seulement un brouillon
        $this->assertTrue(Gate::forUser($admin)->allows('update', $brouillon));
        $this->assertTrue(Gate::forUser($admin)->allows('publish', $brouillon));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $brouillon));
        $this->assertFalse(Gate::forUser($admin)->allows('archive', $brouillon));

        // Une maquette publiée est en lecture seule, ne se supprime pas, mais peut s'archiver
        $this->assertFalse(Gate::forUser($admin)->allows('update', $d['maquette']));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $d['maquette']));
        $this->assertFalse(Gate::forUser($admin)->allows('publish', $d['maquette']));
        $this->assertTrue(Gate::forUser($admin)->allows('archive', $d['maquette']));
    }

    public function test_politique_des_syllabus(): void
    {
        $d = $this->creerDonnees(Statut::Publie, Statut::Publie);
        $autre = $this->creerSyllabusSupplementaire($d);
        $prof = $this->enseignant();
        $admin = $this->administrateur();
        $this->autoriser($d['syllabus'], $prof);

        $this->assertTrue($prof->can('update', $d['syllabus']));
        $this->assertFalse($prof->can('update', $autre));
        $this->assertFalse($prof->can('changerStatut', $d['syllabus']));
        $this->assertFalse($prof->can('manageAutorisations', $d['syllabus']));

        $this->assertTrue($admin->can('update', $autre));
        $this->assertTrue($admin->can('changerStatut', $autre));
        $this->assertTrue($admin->can('manageAutorisations', $autre));
    }

    public function test_les_modeles_exposent_un_identifiant_public_et_masquent_l_id_interne(): void
    {
        $user = User::factory()->create();
        $niveau = Niveau::create(['code' => 'M1', 'libelle' => 'Master 1', 'ordre' => 4]);

        foreach ([$user, $niveau] as $modele) {
            $this->assertSame(26, strlen($modele->public_id));
            $this->assertSame('public_id', $modele->getRouteKeyName());
            $this->assertArrayNotHasKey('id', $modele->toArray());
            $this->assertArrayHasKey('public_id', $modele->toArray());
        }

        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_role_et_activation_ne_sont_pas_assignables_en_masse(): void
    {
        $user = new User([
            'name' => 'Test',
            'email' => 'test@exemple.test',
            'password' => 'MotDePasse2026',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertNull($user->getAttribute('role'));
        $this->assertNull($user->getAttribute('is_active'));
    }
}
