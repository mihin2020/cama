<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le DPO est retiré des mentions légales (demande de la CAMA) : plus d'e-mail DPO, et la
 * phrase d'origine qui renvoyait vers lui est reformulée — seulement si elle n'a pas été
 * réécrite entre-temps (un texte personnalisé n'est jamais modifié).
 */
return new class extends Migration
{
    private const OLD = 'Toute demande est à adresser au Délégué à la protection des données (DPO) de la CAMA, par courrier à la Direction Générale ou par e-mail.';

    private const NEW = 'Toute demande est à adresser à la Direction Générale de la CAMA, par courrier ou par e-mail (voir les coordonnées ci-dessous).';

    public function up(): void
    {
        $row = DB::table('platform_settings')->where('key', 'legal_notice')->first();
        $notice = $row ? json_decode((string) $row->value, true) : null;
        if (! is_array($notice)) {
            return;
        }

        unset($notice['dpoEmail']);
        $notice['articles'] = array_map(function ($article) {
            if (is_array($article) && isset($article['body'])) {
                $article['body'] = str_replace(self::OLD, self::NEW, (string) $article['body']);
            }

            return $article;
        }, $notice['articles'] ?? []);

        DB::table('platform_settings')->where('key', 'legal_notice')
            ->update(['value' => json_encode($notice, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Pas de retour : le DPO n'est plus géré par le module.
    }
};
