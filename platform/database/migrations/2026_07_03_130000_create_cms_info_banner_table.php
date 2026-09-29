<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_info_banner', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(true);
            $table->string('type', 20)->default('info');
            $table->text('message')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_info_banner');
    }
};
