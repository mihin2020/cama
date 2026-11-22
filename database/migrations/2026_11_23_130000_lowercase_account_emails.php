<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adresses des comptes en minuscules (voir NormalizeEmailInput). Un compte n'est pas modifié si une autre
 * adresse identique en minuscules existe déjà : l'unicité prime, l'administrateur tranchera.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin_users', 'assures'] as $table) {
            DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
                $lower = mb_strtolower(trim((string) $row->email));

                if ($lower === '' || $lower === $row->email) {
                    return;
                }

                $taken = DB::table($table)->where('email', $lower)->where('id', '!=', $row->id)->exists();

                if (! $taken) {
                    DB::table($table)->where('id', $row->id)->update(['email' => $lower]);
                }
            });
        }
    }

    public function down(): void
    {
        // Rien à rétablir : la casse d'origine n'est pas conservée.
    }
};
