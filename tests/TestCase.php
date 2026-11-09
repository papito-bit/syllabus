<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : les tests vident la base (RefreshDatabase). Ils ne doivent donc JAMAIS
     * s'exécuter ailleurs que sur SQLite en mémoire, sous peine d'effacer votre base MySQL.
     * Cette vérification a lieu avant toute opération sur la base.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connexion = config('database.default');
        $base = config("database.connections.{$connexion}.database");

        if ($connexion !== 'sqlite' || $base !== ':memory:') {
            throw new RuntimeException(
                'Tests interrompus pour protéger votre base de données : ils doivent utiliser SQLite en mémoire '
                .'(vérifiez le fichier phpunit.xml).'
            );
        }
    }
}
