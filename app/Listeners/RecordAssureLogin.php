<?php

namespace App\Listeners;

use App\Models\Assure;
use App\Models\AssureConnexion;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * Historique des connexions à l'espace assuré. Une connexion est « inhabituelle » quand
 * l'appareil (navigateur + système) n'a jamais été vu pour ce compte, hors toute première
 * connexion. Les reconnexions silencieuses par cookie « Se souvenir de moi » ne comptent pas.
 */
class RecordAssureLogin
{
    private const KEPT = 20;

    public function handle(Login $event): void
    {
        if ($event->guard !== 'assure' || ! $event->user instanceof Assure || Auth::guard('assure')->viaRemember()) {
            return;
        }

        $appareil = self::deviceLabel((string) request()->userAgent());

        $history = AssureConnexion::query()->where('assure_id', $event->user->id);
        $inhabituelle = (clone $history)->exists() && ! (clone $history)->where('appareil', $appareil)->exists();

        AssureConnexion::query()->create([
            'assure_id' => $event->user->id,
            'ip' => request()->ip(),
            'appareil' => $appareil,
            'inhabituelle' => $inhabituelle,
        ]);

        // On ne garde que les dernières connexions.
        $oldest = (clone $history)->latest('id')->skip(self::KEPT)->take(PHP_INT_MAX)->pluck('id');
        if ($oldest->isNotEmpty()) {
            AssureConnexion::query()->whereIn('id', $oldest)->delete();
        }
    }

    /** « Chrome / Windows », « Safari / iPhone »… depuis le User-Agent. */
    public static function deviceLabel(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navigateur inconnu',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'système inconnu',
        };

        return "{$browser} / {$os}";
    }
}
