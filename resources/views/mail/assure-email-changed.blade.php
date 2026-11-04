<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Adresse e-mail modifiée</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    <p style="color: #555; margin-top: 0;">Espace assuré — sécurité du compte</p>

    <p>Bonjour {{ $assure->prenom }} {{ $assure->nom }},</p>

    <p>
        L'adresse e-mail de votre espace assuré CAMA vient d'être remplacée par
        <strong>{{ $newEmailMasked }}</strong>. Cette adresse-ci n'est plus associée à votre compte.
    </p>

    <p style="font-size: 13px; color: #666;">
        Si vous êtes à l'origine de ce changement, vous n'avez rien à faire.
        Sinon, votre compte est peut-être compromis : contactez immédiatement la CAMA.
    </p>
</body>
</html>
