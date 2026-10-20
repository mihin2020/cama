<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Code de connexion au back-office CAMA</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    <p style="color: #555; margin-top: 0;">Back-office — vérification en deux étapes</p>

    <p>Bonjour {{ $admin->prenom }} {{ $admin->nom }},</p>

    <p>
        Une connexion à votre compte du back-office CAMA vient d'être demandée.
        Saisissez le code suivant pour la finaliser :
    </p>

    <p style="margin: 28px 0; text-align: center;">
        <span style="display: inline-block; background: #f4f4f4; border: 1px solid #ddd; border-radius: 8px; padding: 16px 32px; font-size: 32px; font-weight: bold; letter-spacing: 10px; font-family: 'Courier New', monospace;">{{ $code }}</span>
    </p>

    <p style="font-size: 13px; color: #666;">
        Ce code expire sous {{ \App\Services\AdminLogin2faService::EXPIRATION_MINUTES }} minutes et ne peut être saisi que {{ \App\Services\AdminLogin2faService::MAX_ATTEMPTS }} fois.
        Si vous n'êtes pas à l'origine de cette connexion, votre mot de passe est probablement compromis :
        changez-le immédiatement et prévenez l'administrateur de la plateforme.
    </p>
</body>
</html>
