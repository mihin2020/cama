<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 40)->default('Centre de santé');
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('maps_url', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_partners');
    }
};
