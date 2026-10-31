<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Réinitialisation du mot de passe CAMA</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    <p style="color: #555; margin-top: 0;">Réinitialisation de mot de passe</p>

    <p>Bonjour {{ $fullName }},</p>

    <p>Vous avez demandé à réinitialiser le mot de passe de votre accès à {{ $space }} CAMA.</p>

    <p style="margin: 28px 0;">
        <a href="{{ $resetUrl }}"
           style="display: inline-block; background: #006e1c; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold;">
            Choisir un nouveau mot de passe
        </a>
    </p>

    <p style="font-size: 13px; color: #666;">
        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
        <a href="{{ $resetUrl }}" style="color: #006e1c; word-break: break-all;">{{ $resetUrl }}</a>
    </p>

    <p style="font-size: 13px; color: #666; margin-top: 32px;">
        Ce lien expire sous {{ $expiresInMinutes }} minutes et ne peut être utilisé qu'une fois.
        Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste inchangé.
    </p>
</body>
</html>
