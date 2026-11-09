<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Statut;
use App\Models\AnneeUniversitaire;
use App\Models\Ec;
use App\Models\LigneMaquette;
use App\Models\Maquette;
use App\Models\MaquetteColonne;
use App\Models\Niveau;
use App\Models\Parcours;
use App\Models\Semestre;
use App\Models\Syllabus;
use App\Models\SyllabusAutorisation;
use App\Models\UniteEnseignement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Données de DÉMONSTRATION entièrement fictives (préfixe [EXEMPLE]).
 * Réservé à l'environnement local : ce seeder ne s'exécute jamais en production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->warn('DemoSeeder ignoré : réservé à APP_ENV=local.');

            return;
        }

        DB::transaction(function () {
            $annee = AnneeUniversitaire::firstOrCreate(
                ['libelle' => '2026-2027'],
                ['is_courante' => true]
            );

            $l1 = Niveau::where('code', 'L1')->firstOrFail();
            $l2 = Niveau::where('code', 'L2')->firstOrFail();
            $l3 = Niveau::where('code', 'L3')->firstOrFail();
            $s3 = Semestre::where('code', 'S3')->firstOrFail();
            $s4 = Semestre::where('code', 'S4')->firstOrFail();

            // Parcours fictifs
            $pa = Parcours::firstOrCreate(
                ['code' => 'EX-PARCOURS-A'],
                [
                    'nom' => '[EXEMPLE] Parcours A',
                    'description' => 'Parcours fictif créé par le seeder de démonstration.',
                ]
            );
            $pb = Parcours::firstOrCreate(
                ['code' => 'EX-PARCOURS-B'],
                [
                    'nom' => '[EXEMPLE] Parcours B',
                    'description' => 'Parcours fictif créé par le seeder de démonstration.',
                ]
            );
            $pa->niveaux()->syncWithoutDetaching([$l1->id, $l2->id]);
            $pb->niveaux()->syncWithoutDetaching([$l2->id, $l3->id]);

            // Maquette fictive publiée (L2 / Parcours A)
            $maquette = Maquette::firstOrCreate(
                [
                    'niveau_id' => $l2->id,
                    'parcours_id' => $pa->id,
                    'annee_universitaire_id' => $annee->id,
                    'version' => 1,
                ],
                [
                    'titre' => '[EXEMPLE] Maquette de démonstration (données fictives)',
                    'statut' => Statut::Publie,
                    'published_at' => now(),
                ]
            );

            // Exemple de colonne supplémentaire propre à cette maquette
            MaquetteColonne::firstOrCreate(
                ['maquette_id' => $maquette->id, 'cle' => 'observation'],
                ['libelle' => 'Observation', 'ordre' => 1]
            );

            // Chaque UE : [code, semestre, ordre, crédits, coefficient, lignes]
            // Chaque ligne : [code EC, intitulé, CM, TD, TP, crédits, évaluation, observation]
            $plan = [
                ['EX-UE301', $s3, 1, 6, 3, [
                    ['EX-EC3011', "[EXEMPLE] EC d'exemple 1", 20, 20, 0, 3, 'Contrôle continu et examen', 'Exemple de colonne supplémentaire'],
                    ['EX-EC3012', "[EXEMPLE] EC d'exemple 2", 15, 10, 15, 3, 'Examen', null],
                ]],
                ['EX-UE302', $s3, 2, 4, 2, [
                    ['EX-EC3021', "[EXEMPLE] EC d'exemple 3", 20, 10, 10, 4, 'Contrôle continu', null],
                ]],
                ['EX-UE401', $s4, 1, 6, 3, [
                    ['EX-EC4011', "[EXEMPLE] EC d'exemple 4", 25, 15, 10, 6, 'Examen', null],
                ]],
            ];

            $ecs = [];
            foreach ($plan as $indexUe => $defUe) {
                [$codeUe, $semestre, $ordre, $credits, $coef, $lignes] = $defUe;

                $ue = UniteEnseignement::firstOrCreate(
                    ['maquette_id' => $maquette->id, 'semestre_id' => $semestre->id, 'code' => $codeUe],
                    [
                        'intitule' => "[EXEMPLE] Unité d'enseignement ".($indexUe + 1),
                        'ordre' => $ordre,
                        'credits' => $credits,
                        'coefficient' => $coef,
                    ]
                );

                foreach ($lignes as $i => $defLigne) {
                    [$codeEc, $intitule, $cm, $td, $tp, $creditsEc, $evaluation, $observation] = $defLigne;

                    $ec = Ec::firstOrCreate(['code' => $codeEc], ['intitule' => $intitule]);
                    $ecs[$codeEc] = $ec;

                    LigneMaquette::firstOrCreate(
                        ['ue_id' => $ue->id, 'ec_id' => $ec->id],
                        [
                            'ordre' => $i + 1,
                            'heures_cm' => $cm,
                            'heures_td' => $td,
                            'heures_tp' => $tp,
                            'heures_total' => $cm + $td + $tp,
                            'credits' => $creditsEc,
                            'modalite_evaluation' => $evaluation,
                            'donnees_supplementaires' => $observation ? ['observation' => $observation] : null,
                        ]
                    );
                }
            }

            // Deux enseignants fictifs : mot de passe aléatoire, affiché une seule fois
            $enseignants = [];
            foreach ([1, 2] as $n) {
                $email = "enseignant{$n}@exemple.test";
                $user = User::where('email', $email)->first();

                if (! $user) {
                    $motDePasse = Str::random(16);
                    $user = new User([
                        'name' => "[EXEMPLE] Enseignant {$n}",
                        'email' => $email,
                        'password' => $motDePasse,
                    ]);
                    $user->forceFill(['role' => Role::Enseignant, 'is_active' => true])->save();

                    $this->command->warn("Compte de démonstration : {$email} / mot de passe : {$motDePasse} (note-le, il ne sera plus affiché)");
                }

                $enseignants[$n] = $user;
            }

            // Syllabus fictifs : un publié, un brouillon
            $syllabus1 = Syllabus::firstOrCreate(
                ['ec_id' => $ecs['EX-EC3011']->id, 'annee_universitaire_id' => $annee->id],
                [
                    'statut' => Statut::Publie,
                    'objectifs' => "[EXEMPLE] Texte d'exemple : objectifs pédagogiques fictifs.",
                    'prerequis' => '[EXEMPLE] Prérequis fictifs.',
                    'competences' => '[EXEMPLE] Compétences visées fictives.',
                    'resultats_apprentissage' => "[EXEMPLE] Résultats d'apprentissage fictifs.",
                    'programme' => '[EXEMPLE] Programme détaillé fictif.',
                    'methodes_pedagogiques' => '[EXEMPLE] Méthodes pédagogiques fictives.',
                    'evaluation' => "[EXEMPLE] Modalités d'évaluation fictives.",
                    'bibliographie' => '[EXEMPLE] Bibliographie fictive.',
                    'published_at' => now(),
                ]
            );

            Syllabus::firstOrCreate(
                ['ec_id' => $ecs['EX-EC3012']->id, 'annee_universitaire_id' => $annee->id],
                [
                    'statut' => Statut::Brouillon,
                    'objectifs' => '[EXEMPLE] Syllabus en brouillon : seuls les objectifs sont remplis.',
                ]
            );

            // L'enseignant 1 peut modifier le syllabus 1 ; l'enseignant 2 n'a aucune autorisation
            SyllabusAutorisation::firstOrCreate(
                ['syllabus_id' => $syllabus1->id, 'user_id' => $enseignants[1]->id],
                ['est_responsable' => true]
            );
        });
    }
}
