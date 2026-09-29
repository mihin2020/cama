<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('subject', 160)->nullable();
            $table->text('message');
            $table->boolean('consent')->default(false);
            $table->string('status', 30)->default('new');
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('source_page', 120)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('full_name', 160)->nullable();
            $table->string('token', 100)->unique();
            $table->string('source', 120)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_newsletter_subscribers');
        Schema::dropIfExists('cms_contact_messages');
    }
};
