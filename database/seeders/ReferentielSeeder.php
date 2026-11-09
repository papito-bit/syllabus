<?php

namespace Database\Seeders;

use App\Models\Niveau;
use App\Models\Semestre;
use Illuminate\Database\Seeder;

class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $niveaux = [
            ['L1', 'Licence 1', 1],
            ['L2', 'Licence 2', 2],
            ['L3', 'Licence 3', 3],
            ['M1', 'Master 1', 4],
            ['M2', 'Master 2', 5],
        ];

        foreach ($niveaux as [$code, $libelle, $ordre]) {
            Niveau::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'ordre' => $ordre]);
        }

        for ($i = 1; $i <= 10; $i++) {
            Semestre::updateOrCreate(
                ['code' => 'S'.$i],
                ['libelle' => 'Semestre '.$i, 'ordre' => $i]
            );
        }
    }
}
