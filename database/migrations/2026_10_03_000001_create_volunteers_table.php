<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volunteer sign-ups from the /join page and the campaign website. The
 * phone is encrypted with a keyed hash beside it (one row per number);
 * coordinators call them and turn them into field agents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('phone');
            $table->string('phone_hash', 64)->unique();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->json('help')->nullable();
            $table->string('message', 500)->nullable();
            $table->string('source', 20);
            $table->timestamp('consent_at');
            $table->string('consent_version', 10);
            // new → contacted → joined | not_now
            $table->string('status', 12)->default('new')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['lga_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteers');
    }
};
