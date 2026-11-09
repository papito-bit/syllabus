<?php

namespace App\Enums;

enum StatutImportation: string
{
    case Extrait = 'extrait';
    case EnRevision = 'en_revision';
    case Valide = 'valide';
    case Rejete = 'rejete';

    public function libelle(): string
    {
        return match ($this) {
            self::Extrait => 'Extrait',
            self::EnRevision => 'En révision',
            self::Valide => 'Validé',
            self::Rejete => 'Rejeté',
        };
    }
}
