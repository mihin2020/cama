<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identifiant public (UUID) pour toute ressource désignée dans une adresse : le numéro séquentiel
 * (id) reste la clé interne mais n'apparaît plus dans les URL.
 */
return new class extends Migration
{
    private const TABLES = [
        'admin_notifications', 'admin_users', 'assures', 'assure_notifications', 'cms_articles',
        'cms_article_categories', 'cms_contact_messages', 'cms_faq', 'cms_faq_categories', 'cms_key_figures',
        'cms_media', 'cms_menu_items', 'cms_newsletter_batches', 'cms_newsletter_campaigns',
        'cms_newsletter_subscribers', 'cms_pages', 'cms_page_versions', 'cms_partners', 'cms_resources',
        'cms_resource_categories', 'cms_slides', 'dossiers', 'fif_fields', 'fif_sections', 'admin_roles',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable();
            });

            DB::table($table)->whereNull('uuid')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            });

            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable(false)->change();
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropUnique($table.'_uuid_unique');
                    $t->dropColumn('uuid');
                });
            }
        }
    }
};
