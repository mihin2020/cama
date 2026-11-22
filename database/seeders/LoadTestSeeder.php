<?php

namespace Database\Seeders;

use App\Enums\AssureStatut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de données pour le test de montée en charge (docs/dimensionnement-bd.md) : assurés, dossiers,
 * notifications et connexions en grand nombre, insérés par lots (un mot de passe haché une seule fois).
 *
 * À lancer UNIQUEMENT sur une base jetable :
 *
 *   LOAD_TEST_CONFIRM=1 LOAD_ASSURES=50000 php artisan db:seed --class=LoadTestSeeder
 *
 * Comptes créés : assure0001@charge.test … (mot de passe « Charge2026! », 2FA désactivée, e-mail vérifié),
 * administrateur charge-admin@charge.test (même mot de passe).
 */
class LoadTestSeeder extends Seeder
{
    private const PASSWORD = 'Charge2026!';

    private const CHUNK = 1000;

    public function run(): void
    {
        if (app()->environment('production') || env('LOAD_TEST_CONFIRM') !== '1') {
            $this->command?->error('Refusé : base jetable uniquement. Définissez LOAD_TEST_CONFIRM=1 (jamais en production).');

            return;
        }

        $assures = max(10, (int) env('LOAD_ASSURES', 50000));
        $perAssure = max(1, (int) env('LOAD_DOSSIERS_PAR_ASSURE', 3));
        $hash = Hash::make(self::PASSWORD);
        $now = now();

        $gestionnaires = $this->accounts($hash, $now);

        $this->command?->info("Assurés : {$assures}, dossiers : ".($assures * $perAssure));

        for ($offset = 0; $offset < $assures; $offset += self::CHUNK) {
            $count = min(self::CHUNK, $assures - $offset);
            $rows = [];
            for ($i = 1; $i <= $count; $i++) {
                $n = $offset + $i;
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'nom' => 'CHARGE'.$n, 'prenom' => 'Assure', 'sexe' => $n % 2 ? 'Masculin' : 'Féminin',
                    'matricule' => 'CH-'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
                    'numero_cim' => 'CIM'.$n, 'numero_cama' => 'CAMA-CH-'.$n,
                    'numero_fif' => sprintf('26CH%07d', $n),
                    'armee' => 'Armée de Terre', 'region' => '1re Région Militaire',
                    'email' => 'assure'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'@charge.test',
                    'email_verified_at' => $now, 'password' => $hash, 'statut' => AssureStatut::Actif->value,
                    'deux_fa_active' => false, 'journal' => '[]', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('assures')->insert($rows);

            $firstId = (int) DB::table('assures')->where('email', $rows[0]['email'])->value('id');
            $dossiers = [];
            $notifications = [];
            $connexions = [];
            for ($i = 0; $i < $count; $i++) {
                $assureId = $firstId + $i;
                for ($d = 1; $d <= $perAssure; $d++) {
                    $statut = ['Soumis', 'En instruction', 'Validé', 'Pièce manquante demandée', 'Refusé'][($assureId + $d) % 5];
                    $dossiers[] = [
                        'uuid' => (string) Str::uuid(), 'assure_id' => $assureId, 'ref' => sprintf('CH-%d-%d', $assureId, $d),
                        'nom' => 'MEMBRE'.$d, 'prenom' => 'Test', 'lien' => $d === 1 ? 'Conjoint(e)' : 'Enfant biologique',
                        'statut' => $statut, 'gestionnaire' => $gestionnaires[$assureId % count($gestionnaires)],
                        'journal' => '[]', 'pieces' => '[]', 'date_soumission' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                for ($k = 0; $k < 3; $k++) {
                    $notifications[] = [
                        'uuid' => (string) Str::uuid(), 'assure_id' => $assureId, 'type' => 'info', 'titre' => 'Notification '.$k,
                        'contenu' => 'Contenu de charge', 'lu' => $k === 0, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                $connexions[] = ['assure_id' => $assureId, 'ip' => '10.0.0.'.($assureId % 250), 'appareil' => 'k6', 'inhabituelle' => false, 'created_at' => $now];
            }
            DB::table('dossiers')->insert($dossiers);
            DB::table('assure_notifications')->insert($notifications);
            DB::table('assure_connexions')->insert($connexions);
        }
    }

    /** @return list<string> noms affichés des gestionnaires de charge */
    private function accounts(string $hash, \Illuminate\Support\Carbon $now): array
    {
        $names = [];
        foreach (range(1, 20) as $n) {
            $email = sprintf('charge-gest%02d@charge.test', $n);
            DB::table('admin_users')->updateOrInsert(['email' => $email], [
                'uuid' => (string) Str::uuid(), 'nom' => 'GEST'.$n, 'prenom' => 'Charge', 'password' => $hash, 'role' => 'gestionnaire',
                'actif' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $names[] = 'Charge GEST'.$n;
        }

        DB::table('admin_users')->updateOrInsert(['email' => 'charge-admin@charge.test'], [
            'uuid' => (string) Str::uuid(), 'nom' => 'ADMIN', 'prenom' => 'Charge', 'password' => $hash, 'role' => 'administrateur',
            'actif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $names;
    }
}
