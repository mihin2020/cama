<?php

use App\Support\NumeroFif;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('numero_fif', 30)->nullable()->unique()->after('numero_iup');
        });

        // Ligne verrouillée pour attribuer les rangs un par un (FifProfileService).
        DB::table('platform_settings')->insertOrIgnore([
            'key' => 'fif_lock',
            'value' => json_encode(null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assurés existants : numérotés dans l'ordre d'inscription, par année, armée et région.
        $ranks = [];
        DB::table('assures')->orderBy('created_at')->orderBy('id')
            ->select(['id', 'armee', 'region', 'created_at'])
            ->lazy(500)
            ->each(function ($assure) use (&$ranks) {
                $year = $assure->created_at ? (int) substr((string) $assure->created_at, 0, 4) : (int) date('Y');
                $prefix = NumeroFif::prefix($year, $assure->armee, $assure->region);
                $ranks[$prefix] = ($ranks[$prefix] ?? 0) + 1;

                DB::table('assures')->where('id', $assure->id)
                    ->update(['numero_fif' => NumeroFif::format($prefix, $ranks[$prefix])]);
            });
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'fif_lock')->delete();

        Schema::table('assures', function (Blueprint $table) {
            $table->dropUnique(['numero_fif']);
            $table->dropColumn('numero_fif');
        });
    }
};
