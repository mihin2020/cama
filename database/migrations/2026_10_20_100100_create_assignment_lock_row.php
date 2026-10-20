<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ligne verrouillée (SELECT … FOR UPDATE) par DossierAffectationService pour que les
     * affectations automatiques simultanées passent une par une.
     */
    public function up(): void
    {
        DB::table('platform_settings')->insertOrIgnore([
            'key' => 'affectation_lock',
            'value' => json_encode(null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'affectation_lock')->delete();
    }
};
