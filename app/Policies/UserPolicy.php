<?php

namespace App\Policies;

use App\Models\User;

/** Gestion des comptes : réservée aux administrateurs. */
class UserPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->estAdmin();
    }

    public function view(User $acteur, User $cible): bool
    {
        return $acteur->estAdmin();
    }

    public function create(User $acteur): bool
    {
        return $acteur->estAdmin();
    }

    public function update(User $acteur, User $cible): bool
    {
        return $acteur->estAdmin();
    }

    public function resetPassword(User $acteur, User $cible): bool
    {
        return $acteur->estAdmin();
    }
}
