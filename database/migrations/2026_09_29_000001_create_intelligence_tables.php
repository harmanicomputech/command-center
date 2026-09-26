<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Past governorship results (the campaign supplies them), per LGA or
        // per ward. The baseline for strongholds and swing areas.
        Schema::create('past_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('party', 20);
            $table->unsignedInteger('votes');
            $table->timestamps();
            $table->unique(['year', 'lga_id', 'ward_id', 'party']);
        });

        // Segments built in the explorer, kept for messaging and broadcasts.
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('filters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Web Push, one row per device that opted in.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->json('topics');
            $table->string('device')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('past_results');
    }
};
