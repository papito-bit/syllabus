<?php

namespace App\Policies;

use App\Enums\Statut;
use App\Models\Maquette;
use App\Models\User;

class MaquettePolicy
{
    /** Tout le monde peut lister (la liste publique est filtrée sur les maquettes publiées). */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /** Une maquette publiée est publique ; brouillons et archives sont réservés aux administrateurs. */
    public function view(?User $user, Maquette $maquette): bool
    {
        return $maquette->statut === Statut::Publie || ($user?->estAdmin() ?? false);
    }

    public function create(User $user): bool
    {
        return $user->estAdmin();
    }

    /** Une maquette publiée ou archivée est en lecture seule : on ne modifie que les brouillons. */
    public function update(User $user, Maquette $maquette): bool
    {
        return $user->estAdmin() && $maquette->estModifiable();
    }

    public function publish(User $user, Maquette $maquette): bool
    {
        return $user->estAdmin() && $maquette->statut === Statut::Brouillon;
    }

    public function archive(User $user, Maquette $maquette): bool
    {
        return $user->estAdmin() && $maquette->statut === Statut::Publie;
    }

    /** Pas de suppression irréversible d'une maquette officielle : seuls les brouillons se suppriment. */
    public function delete(User $user, Maquette $maquette): bool
    {
        return $user->estAdmin() && $maquette->statut === Statut::Brouillon;
    }
}
