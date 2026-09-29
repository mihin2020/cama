<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('actif');
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('titre');
            $table->text('contenu');
            $table->string('lien')->nullable();
            $table->boolean('lu')->default(false);
            $table->timestamps();
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('format', 20);
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('admin_notifications');

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
