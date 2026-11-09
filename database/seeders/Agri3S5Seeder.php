<?php

namespace Database\Seeders;

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
use App\Models\UniteEnseignement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Charge la maquette AGRI3 (L3, semestre S5) et les 17 syllabus correspondants, tels qu'ils figurent
 * dans les documents de l'établissement (MAQUETTE.docx et SYLLABUS_S5.docx).
 *
 * - Réservé à l'environnement local (et aux tests) : jamais exécuté en production.
 * - Les codes d'EC (EC20-1, EC20-2…) sont PROVISOIRES : le document ne fournit pas de code d'EC.
 * - L'année universitaire (2026-2027) est une hypothèse : le document ne l'indique pas.
 * - Les volumes horaires totaux sont recalculés (Cours + TD + TP), ils ne sont pas recopiés du document.
 * - Rien n'est écrasé : si une maquette existe déjà pour ce niveau, ce parcours et cette année, rien n'est chargé.
 *
 * Utilisation : php artisan db:seed --class=Agri3S5Seeder
 */
class Agri3S5Seeder extends Seeder
{
    private const FICHIER = 'seeders/data/agri3_s5.json';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Agri3S5Seeder ignoré : réservé à APP_ENV=local.');

            return;
        }

        $donnees = json_decode((string) file_get_contents(database_path(self::FICHIER)), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($donnees) {
            $niveau = Niveau::firstOrCreate(
                ['code' => $donnees['niveau']['code']],
                ['libelle' => $donnees['niveau']['libelle'], 'ordre' => $donnees['niveau']['ordre']]
            );
            $semestre = Semestre::firstOrCreate(
                ['code' => $donnees['semestre']['code']],
                ['libelle' => $donnees['semestre']['libelle'], 'ordre' => $donnees['semestre']['ordre']]
            );
            $annee = AnneeUniversitaire::firstOrCreate(
                ['libelle' => $donnees['annee']],
                ['is_courante' => ! AnneeUniversitaire::where('is_courante', true)->exists()]
            );
            $parcours = Parcours::firstOrCreate(
                ['code' => $donnees['parcours']['code']],
                ['nom' => $donnees['parcours']['nom']]
            );
            $parcours->niveaux()->syncWithoutDetaching([$niveau->id]);

            $existe = Maquette::query()
                ->where('niveau_id', $niveau->id)
                ->where('parcours_id', $parcours->id)
                ->where('annee_universitaire_id', $annee->id)
                ->exists();

            if ($existe) {
                $this->command?->warn("Une maquette existe déjà pour {$niveau->code} / {$parcours->code} / {$annee->libelle} : rien n'a été chargé.");

                return;
            }

            $maquette = Maquette::create([
                'niveau_id' => $niveau->id,
                'parcours_id' => $parcours->id,
                'annee_universitaire_id' => $annee->id,
                'version' => 1,
                'statut' => Statut::Publie,
                'published_at' => now(),
            ]);

            // Colonnes propres à cette maquette : « Autres » (avant Total) et « Travail personnel » (après Total)
            foreach ($donnees['colonnes'] as $ordre => $colonne) {
                MaquetteColonne::create([
                    'maquette_id' => $maquette->id,
                    'cle' => $colonne['cle'],
                    'libelle' => $colonne['libelle'],
                    'ordre' => $ordre,
                ]);
            }

            $nombreLignes = 0;

            foreach ($donnees['unites'] as $indexUe => $ue) {
                $unite = UniteEnseignement::create([
                    'maquette_id' => $maquette->id,
                    'semestre_id' => $semestre->id,
                    'code' => $ue['code'],
                    'intitule' => $ue['intitule'],
                    'ordre' => $indexUe + 1,
                    'credits' => $ue['credits'],
                ]);

                foreach ($ue['lignes'] as $indexLigne => $ligne) {
                    $ec = Ec::firstOrCreate(['code' => $ligne['code']], ['intitule' => $ligne['intitule']]);

                    LigneMaquette::create([
                        'ue_id' => $unite->id,
                        'ec_id' => $ec->id,
                        'ordre' => $indexLigne + 1,
                        'heures_cm' => $ligne['cm'],
                        'heures_td' => $ligne['td'],
                        'heures_tp' => $ligne['tp'],
                        'heures_total' => $ligne['cm'] + $ligne['td'] + $ligne['tp'],
                        'donnees_supplementaires' => ['travail_personnel' => (string) $ligne['travail_personnel']],
                    ]);

                    Syllabus::firstOrCreate(
                        ['ec_id' => $ec->id, 'annee_universitaire_id' => $annee->id],
                        [
                            'statut' => Statut::Publie,
                            'objectifs' => $ligne['syllabus']['objectifs'],
                            'prerequis' => $ligne['syllabus']['prerequis'],
                            'programme' => $ligne['syllabus']['programme'],
                            'bibliographie' => $ligne['syllabus']['bibliographie'],
                            'published_at' => now(),
                        ]
                    );

                    $nombreLignes++;
                }
            }

            $this->command?->info("Maquette {$parcours->code} – {$semestre->code} chargée : ".count($donnees['unites'])." UE, {$nombreLignes} EC et {$nombreLignes} syllabus.");
        });
    }
}
