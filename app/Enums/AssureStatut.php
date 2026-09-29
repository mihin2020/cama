<?php

namespace App\Enums;

enum AssureStatut: string
{
    case EnAttenteValidation = 'en_attente_validation';
    case Actif = 'actif';
    case Desactive = 'desactive';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EnAttenteValidation => 'En attente de validation',
            self::Actif => 'Actif',
            self::Desactive => 'Désactivé',
            self::Refuse => 'Refusé',
        };
    }
}
