<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('draft');
            $table->json('sections_json')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('author_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('image_url')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('cms_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_faq', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->string('category', 100);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('icon', 50)->default('edit');
            $table->string('text');
            $table->string('author_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_activity_logs');
        Schema::dropIfExists('cms_faq');
        Schema::dropIfExists('cms_slides');
        Schema::dropIfExists('cms_articles');
        Schema::dropIfExists('cms_pages');
    }
};
