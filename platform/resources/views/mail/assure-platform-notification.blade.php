<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1b1c1c; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 8px; font-size: 12px; color: #5c5f5f; text-transform: uppercase; letter-spacing: 0.04em;">Espace assuré CAMA</p>
    <h1 style="font-size: 20px; margin: 0 0 16px;">{{ $titre }}</h1>
    <p style="margin: 0 0 16px; white-space: pre-line;">{{ $contenu }}</p>
    @if($lien)
        <p style="margin: 24px 0 0;">
            <a href="{{ $lien }}" style="display: inline-block; background: #006e5b; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: bold;">Accéder à mon espace</a>
        </p>
    @endif
    <p style="margin: 32px 0 0; font-size: 12px; color: #5c5f5f;">Caisse d'Assurance Maladie des Armées — message automatique, merci de ne pas répondre à cet e-mail.</p>
</body>
</html>
