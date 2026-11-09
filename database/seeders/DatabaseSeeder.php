<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferentielSeeder::class,  // niveaux et semestres (sûr en production)
            DemoSeeder::class,         // données fictives (local uniquement)
        ]);
    }
}
