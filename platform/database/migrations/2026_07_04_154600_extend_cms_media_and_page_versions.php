<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_media', function (Blueprint $table) {
            $table->string('title')->nullable()->after('original_name');
            $table->string('alt_text')->nullable()->after('title');
            $table->string('folder', 120)->default('general')->after('alt_text');
            $table->string('category', 120)->nullable()->after('folder');
            $table->json('tags')->nullable()->after('category');
            $table->text('description')->nullable()->after('tags');
        });

        Schema::table('cms_page_versions', function (Blueprint $table) {
            $table->string('comment')->nullable()->after('event');
        });
    }

    public function down(): void
    {
        Schema::table('cms_page_versions', function (Blueprint $table) {
            $table->dropColumn('comment');
        });

        Schema::table('cms_media', function (Blueprint $table) {
            $table->dropColumn(['title', 'alt_text', 'folder', 'category', 'tags', 'description']);
        });
    }
};
