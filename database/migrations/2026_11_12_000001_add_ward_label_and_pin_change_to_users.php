<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field agents imported from a list: the ward as the list wrote it (until
 * it matches a ward in the register, App\Services\WardMatcher), and a flag
 * that sends them to choose their own PIN at their first sign-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ward_label', 120)->nullable();
            $table->boolean('must_change_pin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ward_label', 'must_change_pin']);
        });
    }
};
