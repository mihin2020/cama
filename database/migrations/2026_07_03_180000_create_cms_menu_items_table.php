<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('page');
            $table->foreignId('page_id')->nullable()->constrained('cms_pages')->cascadeOnDelete();
            $table->string('label', 160);
            $table->string('url', 1000)->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
    }
};
