<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('platform_settings')->where('key', 'email_templates')->first();

        if (! $row) {
            return;
        }

        $value = json_decode($row->value, true);

        if (! is_array($value) || empty($value['validation'])) {
            return;
        }

        $suffix = " Votre carte d'assuré sera produite sous peu.";

        if (! str_contains($value['validation'], $suffix)) {
            return;
        }

        $value['validation'] = str_replace($suffix, '.', $value['validation']);

        DB::table('platform_settings')->where('key', 'email_templates')->update([
            'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $row = DB::table('platform_settings')->where('key', 'email_templates')->first();

        if (! $row) {
            return;
        }

        $value = json_decode($row->value, true);

        if (! is_array($value) || empty($value['validation'])) {
            return;
        }

        $suffix = " Votre carte d'assuré sera produite sous peu.";

        if (str_contains($value['validation'], $suffix)) {
            return;
        }

        $value['validation'] = rtrim($value['validation'], '.').$suffix;

        DB::table('platform_settings')->where('key', 'email_templates')->update([
            'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
