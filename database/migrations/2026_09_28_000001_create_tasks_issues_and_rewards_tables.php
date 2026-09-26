<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos from the field (task proof, issues, events). Files live on the
        // private disk; pictures are shown only behind sign-in.
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('owner');
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A task for one agent, or for a whole ward (assignee_id null).
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('type', 20)->index();
            $table->text('description')->nullable();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable()->index();
            $table->unsignedInteger('target')->nullable();
            $table->string('target_unit', 20)->nullable();
            $table->string('proof', 10)->default('photo');
            $table->string('status', 12)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Progress updates from the field (from the outbox, so idempotent by UUID).
        Schema::create('task_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('count')->default(0);
            $table->boolean('done')->default(false)->index();
            $table->string('note', 1000)->nullable();
            $table->timestamp('reported_at')->index();
            $table->timestamps();
            $table->index(['task_id', 'user_id']);
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category', 20)->index();
            $table->text('description');
            $table->string('severity', 10)->index();
            $table->unsignedInteger('people_affected')->nullable();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->string('community', 120)->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->index();
            // new → noted → used in a message → addressed (or rejected).
            $table->string('status', 12)->default('new')->index();
            $table->foreignId('status_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_at')->nullable();
            $table->timestamps();
        });

        // Rewards given to weekly winners (airtime, a shout-out).
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_of');
            $table->string('kind', 20);
            $table->string('description', 255)->nullable();
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('task_reports');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('photos');
    }
};
