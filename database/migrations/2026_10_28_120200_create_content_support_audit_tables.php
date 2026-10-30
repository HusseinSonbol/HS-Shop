<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('section_type', 32);
            $table->foreignId('product_collection_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('banner_placement', 80)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::create('homepage_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('heading', 220)->nullable();
            $table->string('subheading', 500)->nullable();
            $table->text('body')->nullable();
            $table->timestamps(6);
            $table->unique(['homepage_section_id', 'locale']);
        });

        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 80);
            $table->string('image_path', 500);
            $table->string('target_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('starts_at', 6)->nullable();
            $table->timestamp('ends_at', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['placement', 'is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('banner_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title', 255)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->timestamps(6);
            $table->unique(['banner_id', 'locale']);
        });

        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('status', 24);
            $table->timestamp('published_at', 6)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'published_at']);
        });

        Schema::create('cms_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_page_id')->constrained()->restrictOnDelete();
            $table->string('locale', 10);
            $table->string('title', 220);
            $table->longText('body');
            $table->string('meta_title', 220)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps(6);
            $table->unique(['cms_page_id', 'locale']);
        });

        Schema::create('about_faqs', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('about_faq_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('about_faq_id')->constrained()->restrictOnDelete();
            $table->string('locale', 10);
            $table->string('question', 500);
            $table->text('answer');
            $table->timestamps(6);
            $table->unique(['about_faq_id', 'locale']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 80);
            $table->string('key', 120);
            $table->json('value');
            $table->boolean('is_public')->default(false);
            $table->timestamps(6);
            $table->unique(['group', 'key']);
        });

        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('moderation_status', 20)->default('pending');
            $table->string('visibility_status', 16)->default('hidden');
            $table->timestamp('submitted_at', 6);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps(6);
            $table->index(['product_id', 'moderation_status', 'visibility_status'], 'reviews_product_moderation_idx');
            $table->index(['customer_id', 'submitted_at']);
        });

        Schema::create('customer_feedback', function (Blueprint $table): void {
            $table->id();
            $table->string('feedback_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sender_name_snapshot', 160)->nullable();
            $table->string('sender_email_snapshot', 190)->nullable();
            $table->text('feedback_text');
            $table->string('moderation_status', 20)->default('pending');
            $table->string('visibility_status', 16)->default('hidden');
            $table->boolean('show_on_homepage')->default(false);
            $table->timestamp('submitted_at', 6);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->text('staff_note')->nullable();
            $table->timestamps(6);
            $table->index(['moderation_status', 'visibility_status', 'submitted_at'], 'feedback_moderation_submitted_idx');
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subject', 220);
            $table->string('status', 24)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('category', 60)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('requester_name', 160);
            $table->string('requester_email', 190)->nullable();
            $table->string('requester_phone', 32)->nullable();
            $table->timestamp('opened_at', 6);
            $table->timestamp('first_response_at', 6)->nullable();
            $table->timestamp('resolved_at', 6)->nullable();
            $table->timestamp('closed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['status', 'priority', 'opened_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['customer_id', 'opened_at']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->restrictOnDelete();
            $table->string('sender_type', 16);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('visibility', 16);
            $table->text('body');
            $table->timestamp('sent_at', 6);
            $table->timestamp('edited_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['support_ticket_id', 'sent_at']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_message_id')->constrained()->restrictOnDelete();
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->char('file_sha256', 64)->nullable();
            $table->timestamp('uploaded_at', 6);
            $table->index(['support_ticket_message_id', 'uploaded_at'], 'ticket_attachment_uploaded_idx');
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('previous_value', 120)->nullable();
            $table->string('new_value', 120)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['support_ticket_id', 'occurred_at']);
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sender_name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('subject', 220)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamp('received_at', 6);
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('handled_at', 6)->nullable();
            $table->unsignedBigInteger('converted_ticket_id')->nullable();
            $table->timestamps(6);
            $table->index(['status', 'received_at']);
            $table->index(['customer_id', 'received_at']);
        });

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->foreign('converted_ticket_id')->references('id')->on('support_tickets')->restrictOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event', 120);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('correlation_id', 36)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropForeign(['converted_ticket_id']);
        });
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('customer_feedback');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('about_faq_translations');
        Schema::dropIfExists('about_faqs');
        Schema::dropIfExists('cms_page_translations');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('banner_translations');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('homepage_section_translations');
        Schema::dropIfExists('homepage_sections');
    }
};
