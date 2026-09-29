<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('grade')->nullable()->after('prenom');
            $table->json('permissions')->nullable()->after('role');
            $table->timestamp('invited_at')->nullable()->after('last_login_at');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('module', 80)->default('admin');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['grade', 'permissions', 'invited_at']);
        });
    }
};
