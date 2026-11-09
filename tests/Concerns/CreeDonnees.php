<?php

namespace Tests\Concerns;

use App\Enums\Statut;
use App\Models\AnneeUniversitaire;
use App\Models\Ec;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Models\Semestre;
use App\Models\Syllabus;
use App\Models\SyllabusAutorisation;
use App\Models\UniteEnseignement;
use App\Models\User;

/** Outils partagés par les tests : petit jeu de données et utilisateurs. */
trait CreeDonnees
{
    /**
     * Crée : niveau L2, un parcours associé, l'année courante, une maquette (1 UE, 1 EC)
     * et le syllabus de cet EC.
     *
     * @return array{niveau: Niveau, parcours: Parcours, annee: AnneeUniversitaire, semestre: Semestre, maquette: Maquette, ue: UniteEnseignement, ec: Ec, ligne: LigneMaquette, syllabus: Syllabus}
     */
    protected function creerDonnees(Statut $statutMaquette = Statut::Publie, Statut $statutSyllabus = Statut::Publie): array
    {
        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2', 'ordre' => 2]);
        $parcours = Parcours::create(['code' => 'P-TEST', 'nom' => 'Parcours de test']);
        $parcours->niveaux()->attach($niveau->id);
        $annee = AnneeUniversitaire::create(['libelle' => '2026-2027', 'is_courante' => true]);
        $semestre = Semestre::create(['code' => 'S3', 'libelle' => 'Semestre 3', 'ordre' => 3]);

        $maquette = Maquette::create([
            'niveau_id' => $niveau->id,
            'parcours_id' => $parcours->id,
            'annee_universitaire_id' => $annee->id,
            'version' => 1,
            'titre' => 'Maquette de test',
            'statut' => $statutMaquette,
            'published_at' => $statutMaquette === Statut::Publie ? now() : null,
        ]);

        $ue = UniteEnseignement::create([
            'maquette_id' => $maquette->id,
            'semestre_id' => $semestre->id,
            'code' => 'UE1',
            'intitule' => 'UE de test',
            'ordre' => 1,
            'credits' => 6,
            'coefficient' => 3,
        ]);

        $ec = Ec::create(['code' => 'EC1', 'intitule' => 'EC de test']);

        $ligne = LigneMaquette::create([
            'ue_id' => $ue->id,
            'ec_id' => $ec->id,
            'ordre' => 1,
            'heures_cm' => 20,
            'heures_td' => 10,
            'heures_tp' => 10,
            'heures_total' => 40,
            'credits' => 4,
            'coefficient' => 2,
            'modalite_evaluation' => 'Examen',
        ]);

        $syllabus = Syllabus::create([
            'ec_id' => $ec->id,
            'annee_universitaire_id' => $annee->id,
            'statut' => $statutSyllabus,
            'objectifs' => 'Objectifs initiaux',
            'published_at' => $statutSyllabus === Statut::Publie ? now() : null,
        ]);

        return compact('niveau', 'parcours', 'annee', 'semestre', 'maquette', 'ue', 'ec', 'ligne', 'syllabus');
    }

    /** Deuxième maquette (autre version) pour les mêmes niveau, parcours et année. */
    protected function creerMaquetteSupplementaire(array $donnees, Statut $statut, int $version = 2): Maquette
    {
        return Maquette::create([
            'niveau_id' => $donnees['niveau']->id,
            'parcours_id' => $donnees['parcours']->id,
            'annee_universitaire_id' => $donnees['annee']->id,
            'version' => $version,
            'titre' => 'Maquette supplémentaire',
            'statut' => $statut,
            'published_at' => $statut === Statut::Publie ? now() : null,
        ]);
    }

    /** Syllabus d'un autre EC (pour vérifier qu'un droit sur l'un ne donne pas accès à l'autre). */
    protected function creerSyllabusSupplementaire(array $donnees, string $codeEc = 'EC2', Statut $statut = Statut::Publie): Syllabus
    {
        $ec = Ec::create(['code' => $codeEc, 'intitule' => 'EC '.$codeEc]);

        return Syllabus::create([
            'ec_id' => $ec->id,
            'annee_universitaire_id' => $donnees['annee']->id,
            'statut' => $statut,
            'objectifs' => 'Objectifs de '.$codeEc,
        ]);
    }

    protected function enseignant(array $attributs = []): User
    {
        return User::factory()->enseignant()->create($attributs);
    }

    protected function administrateur(array $attributs = []): User
    {
        return User::factory()->admin()->create($attributs);
    }

    protected function autoriser(Syllabus $syllabus, User $user, bool $responsable = false): SyllabusAutorisation
    {
        return SyllabusAutorisation::create([
            'syllabus_id' => $syllabus->id,
            'user_id' => $user->id,
            'est_responsable' => $responsable,
        ]);
    }

    /**
     * Se place « en tant que » cet utilisateur pour la requête suivante.
     *
     * Dans un test, les gardes d'authentification gardent l'utilisateur en mémoire d'une
     * requête à l'autre (alors que dans la vraie vie chaque requête est indépendante).
     * On les réinitialise donc pour simuler fidèlement un nouvel utilisateur.
     */
    protected function enTantQue(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user, 'web');
    }

    /** Simule une requête venant du frontend Vue (nécessaire pour la connexion par session). */
    protected function depuisLeFrontend(): static
    {
        return $this->withHeader('Referer', 'http://localhost:5173');
    }
}
