<?php

namespace App\Enums;

enum AdminRole: string
{
    case Gestionnaire = 'gestionnaire';
    case Superviseur = 'superviseur';
    case Administrateur = 'administrateur';
    case Direction = 'direction';

    public function label(): string
    {
        return match ($this) {
            self::Gestionnaire => 'Gestionnaire CAMA',
            self::Superviseur => 'Superviseur / Responsable',
            self::Administrateur => 'Administrateur technique',
            self::Direction => 'Direction Générale',
        };
    }
}
