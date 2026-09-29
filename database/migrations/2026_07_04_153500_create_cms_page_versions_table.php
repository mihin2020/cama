<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('event')->default('save');
            $table->string('title');
            $table->string('slug');
            $table->string('status');
            $table->json('sections_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cms_page_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_versions');
    }
};
