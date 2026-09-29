<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Invitation CAMA</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    <p style="color: #555; margin-top: 0;">Back-office — invitation compte interne</p>

    <p>Bonjour {{ $user->prenom }} {{ $user->nom }},</p>

    <p>
        {{ $invitedBy }} vous a créé un accès au back-office CAMA
        (rôle : <strong>{{ $user->role->label() }}</strong>).
    </p>

    <p>Cliquez sur le bouton ci-dessous pour définir votre mot de passe et activer votre compte :</p>

    <p style="margin: 28px 0;">
        <a href="{{ $setupUrl }}"
           style="display: inline-block; background: #006e1c; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold;">
            Définir mon mot de passe
        </a>
    </p>

    <p style="font-size: 13px; color: #666;">
        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
        <a href="{{ $setupUrl }}" style="color: #006e1c; word-break: break-all;">{{ $setupUrl }}</a>
    </p>

    <p style="font-size: 13px; color: #666; margin-top: 32px;">
        Ce lien expire sous 60 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.
    </p>
</body>
</html>
