<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The campaign structure beyond user accounts: community influence notes
 * (churches, traditional rulers, age grades, town unions, market
 * associations — recorded per ward, never religion per person), and
 * meetings and events with attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->index();
            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->text('contact_phone')->nullable();
            $table->string('relationship', 20)->default('unknown')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('type', 20)->index();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->index();
            $table->string('venue', 160)->nullable();
            $table->unsignedInteger('expected')->nullable();
            // Filled in afterwards.
            $table->string('status', 12)->default('planned')->index();
            $table->unsignedInteger('attendance')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Team members who attended (points, engagement).
        Schema::create('event_user', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('events');
        Schema::dropIfExists('influencers');
    }
};
