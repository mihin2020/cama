<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('cms_articles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('title')->constrained('cms_article_categories')->nullOnDelete();
        });

        $defaults = [
            ['name' => 'Institution', 'sort_order' => 1],
            ['name' => 'Événement', 'sort_order' => 2],
            ['name' => 'Communiqué', 'sort_order' => 3],
            ['name' => 'Partenariat', 'sort_order' => 4],
        ];

        foreach ($defaults as $cat) {
            DB::table('cms_article_categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $map = DB::table('cms_article_categories')->pluck('id', 'name');

        foreach (DB::table('cms_articles')->orderBy('id')->get() as $article) {
            DB::table('cms_articles')->where('id', $article->id)->update([
                'category_id' => $map[$article->category] ?? $map->first(),
            ]);
        }

        Schema::table('cms_articles', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('cms_articles', function (Blueprint $table) {
            $table->string('category', 100)->nullable();
        });

        $map = DB::table('cms_article_categories')->pluck('name', 'id');

        foreach (DB::table('cms_articles')->orderBy('id')->get() as $article) {
            DB::table('cms_articles')->where('id', $article->id)->update([
                'category' => $map[$article->category_id] ?? 'Institution',
            ]);
        }

        Schema::table('cms_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('cms_article_categories');
    }
};
