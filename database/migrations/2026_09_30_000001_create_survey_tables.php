<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('intro')->nullable();
            // draft → live → closed
            $table->string('status', 10)->default('draft')->index();
            $table->json('lga_ids')->nullable();
            $table->json('ward_ids')->nullable();
            $table->unsignedInteger('quota_per_ward')->nullable();
            $table->json('channels');
            // The public web link, and the SMS keyword for text polls.
            $table->string('web_token', 40)->unique();
            $table->string('sms_keyword', 20)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // single, multiple, rating, text, issue, intention
            $table->string('type', 12);
            $table->string('prompt', 255);
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            // field, web, sms, ussd
            $table->string('channel', 8)->index();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gender', 10)->nullable();
            $table->string('age_band', 10)->nullable();
            $table->string('occupation', 20)->nullable();
            // One response per phone per survey (a keyed hash, never the number).
            $table->string('phone_hash', 64)->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('answers');
            $table->timestamp('answered_at')->index();
            $table->timestamps();
            $table->unique(['survey_id', 'phone_hash']);
            $table->index(['survey_id', 'ward_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
