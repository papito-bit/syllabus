<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Enseignant = 'enseignant';

    public function libelle(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Enseignant => 'Enseignant',
        };
    }
}
