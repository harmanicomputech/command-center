<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geography is the backbone: every voter, task, issue and survey response
 * belongs to a ward, and so to an LGA. Polling units are optional detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedInteger('registered_voters')->default(0);
            $table->unsignedInteger('wards_count')->default(0);
            $table->unsignedInteger('polling_units_count')->default(0);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lga_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('registered_voters')->default(0);
            $table->unsignedInteger('polling_units_count')->default(0);
            $table->timestamps();
            $table->unique(['lga_id', 'slug']);
            $table->unique(['lga_id', 'name']);
        });

        Schema::create('polling_units', function (Blueprint $table) {
            $table->id();
            // INEC codes like EB/212/02633/007 are stored as digits.
            $table->string('code', 20)->unique();
            $table->string('name')->nullable();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('registered_voters')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_units');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('lgas');
    }
};
