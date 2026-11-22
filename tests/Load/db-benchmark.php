<?php

/**
 * Mesure de la base PostgreSQL SANS serveur web : les vraies requêtes de l'application (services) sur le jeu de
 * données de charge, d'abord une par une (latence), puis en parallèle (débit et contention).
 *
 *   DB_DATABASE=cama_load DB_PASSWORD=... php tests/Load/db-benchmark.php [processus=8] [secondes=20]
 *
 * Base jetable uniquement (voir database/seeders/LoadTestSeeder.php). Ne mesure ni nginx, ni PHP-FPM, ni le
 * réseau : pour cela, utiliser les scénarios k6 (docs/dimensionnement-bd.md).
 */

use App\Models\AdminUser;
use App\Models\Assure;
use App\Services\AdminAssureService;
use App\Services\AdminDossierService;
use App\Services\AssureDashboardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_contains((string) config('database.connections.pgsql.database'), 'load') && getenv('LOAD_TEST_CONFIRM') !== '1') {
    fwrite(STDERR, "Refusé : la base doit s'appeler cama_load… (ou LOAD_TEST_CONFIRM=1).\n");
    exit(1);
}

/** Les scénarios : nom => fonction qui exécute le travail d'UNE requête web de ce type. */
function scenarios(): array
{
    $assureCount = (int) DB::table('assures')->count();

    return [
        'assuré : tableau de bord' => function () use ($assureCount) {
            $assure = Assure::query()->where('email', sprintf('assure%04d@charge.test', random_int(1, min($assureCount, 9999))))->first();
            app(AssureDashboardService::class)->stats($assure);
            $assure->notifications()?->where('lu', false)->count();
        },
        'assuré : connexion (lecture compte + historique)' => function () use ($assureCount) {
            $assure = Assure::query()->where('email', sprintf('assure%04d@charge.test', random_int(1, min($assureCount, 9999))))->first();
            DB::table('assure_connexions')->where('assure_id', $assure->id)->orderByDesc('created_at')->limit(10)->get();
        },
        'admin : liste des assurés (page de 20, recherche)' => function () {
            app(AdminAssureService::class)->paginate(['search' => 'CHARGE'.random_int(1, 4999)], 20);
        },
        'admin : liste des assurés (page 1, sans recherche)' => function () {
            app(AdminAssureService::class)->paginate([], 20);
        },
        'gestionnaire : ses dossiers (≈ 1/20 du total)' => function () {
            ini_set('memory_limit', '-1');
            $admin = AdminUser::query()->where('email', 'charge-gest'.str_pad((string) random_int(1, 20), 2, '0', STR_PAD_LEFT).'@charge.test')->first();
            $service = app(AdminDossierService::class);
            $service->groupFamilies($service->scopedDossiers($admin));
        },
    ];
}

function percentile(array $values, float $p): float
{
    sort($values);

    return $values ? $values[(int) min(count($values) - 1, floor(count($values) * $p))] : 0.0;
}

// ---------------------------------------------------------------- mode « worker » (processus parallèle)
if (($argv[1] ?? '') === 'worker') {
    $until = microtime(true) + (float) $argv[2];
    $mix = ['assuré : tableau de bord' => 5, 'assuré : connexion (lecture compte + historique)' => 2, 'admin : liste des assurés (page 1, sans recherche)' => 1, 'admin : liste des assurés (page de 20, recherche)' => 1];
    $pool = [];
    foreach ($mix as $name => $weight) {
        $pool = array_merge($pool, array_fill(0, $weight, $name));
    }
    $all = scenarios();
    $times = [];
    $errors = 0;
    while (microtime(true) < $until) {
        $name = $pool[array_rand($pool)];
        $t = microtime(true);
        try {
            $all[$name]();
            $times[] = (microtime(true) - $t) * 1000;
        } catch (Throwable $e) {
            $errors++;
        }
    }
    echo json_encode(['times' => $times, 'errors' => $errors]);
    exit(0);
}

// ---------------------------------------------------------------- mode principal
$workers = (int) ($argv[1] ?? 8);
$seconds = (int) ($argv[2] ?? 20);

$size = DB::selectOne('select pg_size_pretty(pg_database_size(current_database())) as s')->s;
echo 'Base : '.config('database.connections.pgsql.database').' ('.$size.') — '
    .DB::table('assures')->count().' assurés, '.DB::table('dossiers')->count().' dossiers'."\n\n";

echo "1) Latence, un processus (30 requêtes par scénario, millisecondes)\n";
printf("%-58s %8s %8s %8s\n", 'scénario', 'moy', 'p95', 'max');
foreach (scenarios() as $name => $run) {
    $run(); // échauffement
    $times = [];
    $iterations = str_contains($name, 'gestionnaire') ? 5 : 30;
    for ($i = 0; $i < $iterations; $i++) {
        $t = microtime(true);
        $run();
        $times[] = (microtime(true) - $t) * 1000;
    }
    printf("%-58s %8.1f %8.1f %8.1f\n", $name, array_sum($times) / count($times), percentile($times, 0.95), max($times));
}

echo "\n2) Parallèle : {$workers} processus PHP pendant {$seconds} s (mélange : 62 % tableau de bord, 25 % connexion, 13 % listes admin)\n";
$procs = [];
for ($i = 0; $i < $workers; $i++) {
    $cmd = [PHP_BINARY, __FILE__, 'worker', (string) $seconds];
    $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$times = [];
$errors = 0;
$peak = 0;
$deadline = microtime(true) + $seconds;
while (microtime(true) < $deadline) {
    $peak = max($peak, (int) DB::selectOne("select count(*) c from pg_stat_activity where datname = current_database() and state = 'active'")->c);
    usleep(250000);
}
foreach ($procs as $i => $proc) {
    $out = stream_get_contents($pipes[$i][1]);
    proc_close($proc);
    $data = json_decode($out, true);
    if ($data) {
        $times = array_merge($times, $data['times']);
        $errors += $data['errors'];
    }
}
$n = count($times);
printf("Requêtes réussies : %d (%.0f / s) | erreurs : %d | connexions actives max : %d\n", $n, $n / $seconds, $errors, $peak);
printf("Latence : moy %.1f ms | p95 %.1f ms | p99 %.1f ms | max %.1f ms\n", $n ? array_sum($times) / $n : 0, percentile($times, 0.95), percentile($times, 0.99), $times ? max($times) : 0);
echo "Cache PostgreSQL : ".DB::selectOne("select round(100.0 * sum(blks_hit) / nullif(sum(blks_hit + blks_read), 0), 2) c from pg_stat_database where datname = current_database()")->c." %\n";
