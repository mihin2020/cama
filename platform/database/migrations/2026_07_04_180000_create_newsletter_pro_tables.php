<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_newsletter_subscribers', function (Blueprint $table) {
            $table->string('segment', 40)->default('visiteur')->after('full_name');
            $table->string('email_status', 20)->default('active')->after('segment');
            $table->unsignedInteger('open_count')->default(0)->after('email_status');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
            $table->timestamp('last_opened_at')->nullable()->after('click_count');
            $table->timestamp('last_clicked_at')->nullable()->after('last_opened_at');
            $table->timestamp('last_sent_at')->nullable()->after('last_clicked_at');
            $table->json('tags')->nullable()->after('last_sent_at');
        });

        Schema::create('cms_newsletter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('blocks_json');
            $table->longText('html_preview')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('cms_newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader', 255)->nullable();
            $table->json('blocks_json')->nullable();
            $table->longText('html_body')->nullable();
            $table->string('segment', 40)->nullable();
            $table->json('batch_ids')->nullable();
            $table->json('subscriber_ids')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('unsubscribe_count')->default(0);
            $table->foreignId('template_id')->nullable()->constrained('cms_newsletter_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_newsletter_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('cms_newsletter_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('cms_newsletter_subscribers')->cascadeOnDelete();
            $table->string('tracking_token', 64)->unique();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_newsletter_campaign_sends');
        Schema::dropIfExists('cms_newsletter_campaigns');
        Schema::dropIfExists('cms_newsletter_templates');

        Schema::table('cms_newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn([
                'segment', 'email_status', 'open_count', 'click_count',
                'last_opened_at', 'last_clicked_at', 'last_sent_at', 'tags',
            ]);
        });
    }
};
