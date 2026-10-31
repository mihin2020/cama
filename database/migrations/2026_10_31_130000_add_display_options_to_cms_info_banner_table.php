<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options d'affichage du bandeau d'information : fermeture mémorisée par le
 * visiteur, défilement du texte (vitesse en secondes par passage) et couleurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_info_banner', function (Blueprint $table) {
            $table->boolean('dismissible')->default(true)->after('type');
            $table->boolean('scrolling')->default(false)->after('dismissible');
            $table->unsignedSmallInteger('scroll_speed')->default(25)->after('scrolling');
            $table->string('bg_color', 7)->nullable()->after('scroll_speed');
            $table->string('text_color', 7)->nullable()->after('bg_color');
        });
    }

    public function down(): void
    {
        Schema::table('cms_info_banner', function (Blueprint $table) {
            $table->dropColumn(['dismissible', 'scrolling', 'scroll_speed', 'bg_color', 'text_color']);
        });
    }
};
