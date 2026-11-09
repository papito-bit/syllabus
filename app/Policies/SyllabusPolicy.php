<?php

namespace App\Policies;

use App\Enums\Statut;
use App\Models\Syllabus;
use App\Models\User;

class SyllabusPolicy
{
    /**
     * Un syllabus publié est public. Un brouillon ou un archivé n'est visible que
     * par un administrateur ou par un enseignant autorisé sur ce syllabus.
     */
    public function view(?User $user, Syllabus $syllabus): bool
    {
        if ($syllabus->statut === Statut::Publie) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->estAdmin() || $syllabus->estAutorisePour($user);
    }

    /**
     * Modification du contenu : administrateur, ou enseignant ACTIF disposant d'une
     * autorisation non révoquée sur CE syllabus précis. Un syllabus archivé n'est plus
     * modifiable par un enseignant.
     */
    public function update(User $user, Syllabus $syllabus): bool
    {
        if ($user->estAdmin()) {
            return true;
        }

        return $user->estEnseignant()
            && $user->is_active
            && $syllabus->statut !== Statut::Archive
            && $syllabus->estAutorisePour($user);
    }

    /** Publier, archiver ou remettre en brouillon : réservé aux administrateurs. */
    public function changerStatut(User $user, Syllabus $syllabus): bool
    {
        return $user->estAdmin();
    }

    public function manageAutorisations(User $user, Syllabus $syllabus): bool
    {
        return $user->estAdmin();
    }
}
