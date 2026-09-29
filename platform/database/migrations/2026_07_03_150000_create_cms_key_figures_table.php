<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_key_figures', function (Blueprint $table) {
            $table->id();
            $table->decimal('value', 12, 2);
            $table->string('suffix', 10)->nullable();
            $table->string('label');
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_key_figures');
    }
};
