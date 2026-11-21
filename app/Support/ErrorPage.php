<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pages d'erreur personnalisées (403, 404, 419, 429, 500, 503).
 *
 * Les réponses d'erreur HTML sont remplacées par la page Inertia « Errors/Show », à l'image du
 * reste du site. Si ce rendu échoue (ex. erreur pendant le rendu lui-même), la réponse d'origine
 * est conservée : Laravel utilise alors les gabarits Blade de secours de resources/views/errors.
 */
final class ErrorPage
{
    public const STATUSES = [403, 404, 419, 429, 500, 503];

    public static function respond(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true) || ! self::wantsHtml($request)) {
            return $response;
        }

        // En développement, on garde la page de débogage de Laravel pour les erreurs serveur.
        if ($status >= 500 && config('app.debug')) {
            return $response;
        }

        // Formulaire soumis après expiration de la session : on revient sur la page avec un message
        // plutôt que d'afficher une impasse.
        if ($status === 419) {
            return back()->with('error', 'Votre session a expiré. Veuillez réessayer.');
        }

        try {
            $page = Inertia::render('Errors/Show', [
                'status' => $status,
                'espace' => self::space($request),
                'actions' => self::actions($request),
            ])->toResponse($request);
        } catch (Throwable) {
            return $response;
        }

        $page->setStatusCode($status);

        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        return $page;
    }

    /** Les appels d'API et les requêtes JSON gardent une réponse JSON. */
    private static function wantsHtml(Request $request): bool
    {
        if ($request->is('api/*')) {
            return false;
        }

        if ($request->header('X-Inertia')) {
            return true;
        }

        return ! $request->expectsJson();
    }

    /** Espace dans lequel l'erreur s'est produite : adapte les liens et le texte de la page. */
    public static function space(Request $request): string
    {
        if ($request->is('admin', 'admin/*')) {
            return 'admin';
        }

        if ($request->is('espace-assure', 'espace-assure/*')) {
            return 'assure';
        }

        return 'site';
    }

    /**
     * Liens proposés pour quitter la page d'erreur.
     *
     * @return list<array{label: string, href: string, primary: bool}>
     */
    private static function actions(Request $request): array
    {
        $actions = match (self::space($request)) {
            'admin' => auth('admin')->check()
                ? [['label' => 'Tableau de bord', 'href' => route('admin.dashboard')]]
                : [['label' => 'Connexion administration', 'href' => route('admin.login')]],
            'assure' => auth('assure')->check()
                ? [['label' => 'Mon espace assuré', 'href' => route('assure.dashboard')]]
                : [['label' => 'Connexion assuré', 'href' => route('assure.login')]],
            default => [],
        };

        $actions[] = ['label' => "Page d'accueil", 'href' => route('home')];

        return array_map(
            fn (array $action, int $index) => [...$action, 'primary' => $index === 0],
            $actions,
            array_keys($actions),
        );
    }
}
