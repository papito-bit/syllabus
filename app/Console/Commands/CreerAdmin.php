<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Procédure sécurisée de création d'un administrateur.
 * Le mot de passe est saisi de façon masquée et n'est jamais passé en argument
 * (il n'apparaît donc ni dans l'historique du terminal ni dans Git).
 */
class CreerAdmin extends Command
{
    protected $signature = 'admin:creer
                            {--nom= : Nom complet de l\'administrateur}
                            {--email= : Adresse e-mail de connexion}';

    protected $description = 'Crée un compte administrateur (mot de passe saisi de façon masquée)';

    public function handle(AuditService $audit): int
    {
        $nom = trim((string) ($this->option('nom') ?: $this->ask('Nom complet')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Adresse e-mail'))));
        $motDePasse = (string) $this->secret('Mot de passe (10 caractères minimum, avec majuscule, minuscule et chiffre)');
        $confirmation = (string) $this->secret('Confirmez le mot de passe');

        $validateur = Validator::make(
            [
                'name' => $nom,
                'email' => $email,
                'password' => $motDePasse,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            ],
            [],
            [
                'name' => 'nom',
                'email' => 'adresse e-mail',
                'password' => 'mot de passe',
            ]
        );

        if ($validateur->fails()) {
            $this->newLine();
            foreach ($validateur->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $admin = DB::transaction(function () use ($nom, $email, $motDePasse, $audit) {
            $user = new User(['name' => $nom, 'email' => $email, 'password' => $motDePasse]);
            // role et is_active ne sont pas assignables en masse : on les fixe explicitement ici
            $user->forceFill(['role' => Role::Admin, 'is_active' => true])->save();

            $audit->journaliser(
                null,
                'creation_admin',
                $user,
                null,
                ['email' => $user->email, 'role' => Role::Admin->value],
                'Création du compte administrateur en ligne de commande'
            );

            return $user;
        });

        $this->newLine();
        $this->info("Administrateur créé : {$admin->email}");

        return self::SUCCESS;
    }
}
