<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('cms_faq', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('answer')->constrained('cms_faq_categories')->nullOnDelete();
        });

        $defaults = [
            ['name' => 'Enrôlement', 'sort_order' => 1],
            ['name' => 'Prestations', 'sort_order' => 2],
            ['name' => 'Remboursements', 'sort_order' => 3],
            ['name' => 'Compte assuré', 'sort_order' => 4],
        ];

        foreach ($defaults as $cat) {
            DB::table('cms_faq_categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $map = DB::table('cms_faq_categories')->pluck('id', 'name');

        foreach (DB::table('cms_faq')->orderBy('id')->get() as $faq) {
            DB::table('cms_faq')->where('id', $faq->id)->update([
                'category_id' => $map[$faq->category] ?? $map->first(),
            ]);
        }

        Schema::table('cms_faq', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('cms_faq', function (Blueprint $table) {
            $table->string('category', 100)->nullable();
        });

        $map = DB::table('cms_faq_categories')->pluck('name', 'id');

        DB::table('cms_faq')->orderBy('id')->get()->each(function ($faq) use ($map) {
            DB::table('cms_faq')->where('id', $faq->id)->update([
                'category' => $map[$faq->category_id] ?? 'Enrôlement',
            ]);
        });

        Schema::table('cms_faq', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('cms_faq_categories');
    }
};
