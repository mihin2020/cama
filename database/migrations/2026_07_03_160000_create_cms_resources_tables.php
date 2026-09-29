<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_resource_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('cms_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('cms_resource_categories')->cascadeOnDelete();
            $table->string('format', 20)->default('PDF');
            $table->string('file_size', 30)->nullable();
            $table->string('file_url', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_resources');
        Schema::dropIfExists('cms_resource_categories');
    }
};
