<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The face check on registration (App\Support\FaceCheck): its outcome, the
 * prompts passed (no face measurements are ever stored), and when the face
 * photo arrived. Existing records are "not_required": they were made before
 * the check and keep their points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->string('face_status', 12)->default('not_required')->index();
            $table->json('face_check')->nullable();
            $table->timestamp('face_photo_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex(['face_status']);
            $table->dropColumn(['face_status', 'face_check', 'face_photo_at']);
        });
    }
};
