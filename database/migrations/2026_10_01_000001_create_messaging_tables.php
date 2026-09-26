<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: the policy knowledge base, AI drafts (with who approved them),
 * the AI call log (tokens and cost), SMS broadcasts to segments with STOP
 * opt-outs, the narratives feed, the news tracker and our own page posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL can't roll back CREATE TABLE, so a failed earlier attempt of
        // this migration (it isn't recorded as run) may have left some of its
        // tables behind. Only this migration creates them: start clean.
        $this->down();

        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 30)->index();
            $table->string('title', 160);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Every call to Claude: what for, the model that answered, tokens and cost.
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 30)->index();
            $table->string('model', 60);
            $table->string('status', 12);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->index();
        });

        Schema::create('message_drafts', function (Blueprint $table) {
            $table->id();
            // The audience is a description (segment filters and counts), never people.
            $table->json('filters')->nullable();
            $table->string('audience', 255);
            $table->unsignedInteger('audience_size')->default(0);
            $table->string('goal', 500);
            $table->string('channel', 20);
            $table->string('language', 10);
            $table->string('tone', 20)->nullable();
            // queued → ready | failed → approved | rejected
            $table->string('status', 12)->default('queued')->index();
            $table->json('variants')->nullable();
            $table->string('error', 255)->nullable();
            $table->foreignId('ai_call_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('chosen')->nullable();
            $table->text('final_text')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('message');
            // {"type": "voters", "filters": {...}} or {"type": "team", "roles": [...]}
            $table->json('audience');
            $table->string('audience_label', 255);
            $table->foreignId('message_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('draft')->index();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedTinyInteger('parts')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // One row per recipient: the voter or team member, never a copied phone number.
        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type', 10);
            $table->unsignedBigInteger('recipient_id');
            $table->string('status', 12)->default('queued')->index();
            $table->string('provider_id', 100)->nullable()->index();
            $table->string('cost', 30)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            // Named explicitly: MySQL caps identifiers at 64 characters.
            $table->unique(['broadcast_id', 'recipient_type', 'recipient_id'], 'broadcast_messages_recipient_unique');
        });

        // STOP replies, by keyed phone hash: stays even if the voter record is erased.
        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_hash', 64)->unique();
            $table->string('source', 30);
            $table->timestamp('created_at');
        });

        Schema::create('narratives', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('topic', 30)->index();
            $table->string('tone', 10);
            // new → watching → responding → closed
            $table->string('status', 12)->default('new')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('narrative_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('narrative_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->text('summary');
            $table->string('link', 500)->nullable();
            $table->foreignId('lga_id')->nullable()->constrained();
            $table->foreignId('ward_id')->nullable()->constrained();
            $table->string('topic', 30);
            $table->string('tone', 10);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('seen_at')->index();
            $table->timestamps();
        });

        Schema::create('news_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('url', 500);
            $table->boolean('active')->default(true);
            $table->timestamp('fetched_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_feed_id')->constrained()->cascadeOnDelete();
            $table->string('guid_hash', 64)->unique();
            $table->string('title', 300);
            $table->string('link', 500)->nullable();
            $table->text('summary')->nullable();
            $table->json('keywords')->nullable();
            $table->timestamp('published_at')->index();
            $table->boolean('starred')->default(false);
            $table->timestamps();
        });

        Schema::create('page_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->timestamp('posted_at')->index();
            $table->string('text', 500);
            $table->string('link', 500)->nullable();
            $table->string('topic', 30)->nullable();
            $table->unsignedInteger('reach')->nullable();
            $table->unsignedInteger('reactions')->nullable();
            $table->unsignedInteger('comments')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['page_posts', 'news_items', 'news_feeds', 'narrative_reports', 'narratives', 'sms_opt_outs', 'broadcast_messages', 'broadcasts', 'message_drafts', 'ai_calls', 'policy_documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
