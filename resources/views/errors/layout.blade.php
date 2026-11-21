{{--
    Page d'erreur de secours, utilisée seulement quand la page Inertia « Errors/Show » ne peut pas
    être affichée (ex. erreur pendant le rendu). Aucun JavaScript ni ressource externe : tout est ici.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') - CAMA</title>
    <style>
        :root { --primary: #9e001f; --text: #1b1c1c; --muted: #5b403f; --bg: #fbf9f8; --card: #ffffff; --border: #e4bebc; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
               padding: 48px 16px; background: var(--bg); color: var(--text);
               font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        img { width: 64px; height: 64px; object-fit: contain; margin-bottom: 32px; }
        main { width: 100%; max-width: 32rem; background: var(--card); border: 1px solid var(--border);
               border-radius: 16px; padding: 32px; text-align: center; }
        .code { margin: 0; font-size: 3rem; font-weight: 800; color: var(--primary); letter-spacing: -0.02em; }
        h1 { margin: 8px 0 0; font-size: 1.5rem; }
        p.text { margin: 12px 0 0; font-size: .875rem; line-height: 1.6; color: var(--muted); }
        .actions { margin-top: 32px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        a.btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-size: .875rem; font-weight: 600;
                text-decoration: none; border: 1px solid var(--border); color: var(--text); }
        a.btn.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        footer { margin-top: 24px; font-size: .75rem; color: var(--muted); }
    </style>
</head>
<body>
    <img src="{{ asset('images/logo_cama.png') }}" alt="Logo CAMA">
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="text">@yield('message')</p>
        <div class="actions">
            @if (request()->is('admin', 'admin/*'))
                <a class="btn primary" href="{{ url('/admin') }}">Espace administration</a>
            @elseif (request()->is('espace-assure', 'espace-assure/*'))
                <a class="btn primary" href="{{ url('/espace-assure') }}">Mon espace assuré</a>
            @endif
            <a class="btn @unless (request()->is('admin', 'admin/*', 'espace-assure', 'espace-assure/*')) primary @endunless" href="{{ url('/') }}">Page d'accueil</a>
        </div>
    </main>
    <footer>Caisse d'Assurance Maladie des Armées (CAMA)</footer>
</body>
</html>
