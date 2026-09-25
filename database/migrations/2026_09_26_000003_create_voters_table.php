<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voters registered by the field (canvassing). Political opinion is
 * sensitive personal data under the NDPA: the phone number is encrypted at
 * rest with a keyed hash beside it for de-duplication, age is a band, and
 * nothing about religion, ethnicity or voter cards is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            // Generated on the phone, so syncing twice never makes two records.
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->string('gender', 10)->nullable();
            $table->string('age_band', 10)->nullable()->index();
            $table->string('occupation', 20)->nullable()->index();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('community', 120)->nullable();
            $table->string('support_level', 20)->index();
            $table->string('top_issue', 30)->nullable()->index();
            $table->text('notes')->nullable();

            // Consent: who captured it, when, and which text they agreed to.
            $table->timestamp('consent_at');
            $table->string('consent_version', 10);

            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->index();

            // Optional GPS, rounded (about 100 m): only to confirm the ward.
            $table->decimal('latitude', 7, 3)->nullable();
            $table->decimal('longitude', 7, 3)->nullable();

            // unverified → verified | invalid (coordinator spot-checks).
            $table->string('status', 12)->default('unverified')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_note', 255)->nullable();

            // A possible duplicate (same phone) waits for a coordinator.
            $table->foreignId('duplicate_of')->nullable()->constrained('voters')->nullOnDelete();
            $table->boolean('possible_duplicate')->default(false)->index();

            // Opt-out and erasure ("delete my data" keeps anonymous counts).
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('erased_at')->nullable();

            $table->timestamps();
            $table->index(['ward_id', 'captured_at']);
            $table->index(['captured_by', 'captured_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Invites: the agent opens a link and chooses a PIN.
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_accepted_at')->nullable();
            // "Sign out this person's devices" (a lost phone).
            $table->timestamp('sessions_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['invite_token_hash']);
            $table->dropColumn(['invite_token_hash', 'invite_expires_at', 'invite_accepted_at', 'sessions_revoked_at']);
        });
        Schema::dropIfExists('voters');
    }
};
