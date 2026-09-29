<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Code de vérification CAMA</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    <p style="color: #555; margin-top: 0;">Espace assuré — vérification de votre adresse e-mail</p>

    <p>Bonjour {{ $assure->prenom }} {{ $assure->nom }},</p>

    <p>
        Votre demande d'inscription à l'espace assuré CAMA a bien été enregistrée.
        Pour confirmer votre adresse e-mail, saisissez le code suivant :
    </p>

    <p style="margin: 28px 0; text-align: center;">
        <span style="display: inline-block; background: #f4f4f4; border: 1px solid #ddd; border-radius: 8px; padding: 16px 32px; font-size: 32px; font-weight: bold; letter-spacing: 12px; font-family: 'Courier New', monospace;">{{ $code }}</span>
    </p>

    <p style="font-size: 13px; color: #666;">
        Ce code expire sous 15 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.
    </p>

    <p style="font-size: 13px; color: #666; margin-top: 32px;">
        La vérification de l'e-mail ne remplace pas la validation administrative de votre compte,
        qui sera effectuée par un gestionnaire CAMA.
    </p>
</body>
</html>
