<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_partners', function (Blueprint $table) {
            $table->text('address')->nullable()->after('city');
            $table->string('phone', 60)->nullable()->after('address');
            $table->string('email', 190)->nullable()->after('phone');
            $table->string('hours', 255)->nullable()->after('email');
        });

        Schema::table('cms_contact_messages', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('handled_at');
        });

        Schema::create('cms_newsletter_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cms_newsletter_batch_subscriber', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('cms_newsletter_batches')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('cms_newsletter_subscribers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_newsletter_batch_subscriber');
        Schema::dropIfExists('cms_newsletter_batches');

        Schema::table('cms_contact_messages', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::table('cms_partners', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'email', 'hours']);
        });
    }
};
