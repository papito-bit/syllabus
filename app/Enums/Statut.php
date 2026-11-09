<?php

namespace App\Enums;

/** Statut d'une maquette ou d'un syllabus. */
enum Statut: string
{
    case Brouillon = 'brouillon';
    case Publie = 'publie';
    case Archive = 'archive';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Publie => 'Publié',
            self::Archive => 'Archivé',
        };
    }
}
