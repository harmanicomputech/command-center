<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete my data" requests (NDPA): from the privacy page (checked by
 * phone before anything is erased), by SMS "DELETE" (the sender's number
 * proves it's theirs), or recorded by staff. Erasure clears personal
 * fields and keeps anonymous counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);
            // Kept encrypted only until the request is closed, to call the person back.
            $table->text('phone')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->string('name', 120)->nullable();
            $table->string('note', 500)->nullable();
            // pending → done | rejected
            $table->string('status', 10)->default('pending')->index();
            $table->unsignedInteger('erased')->default(0);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
